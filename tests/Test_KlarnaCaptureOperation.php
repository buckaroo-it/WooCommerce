<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaKpGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Gateways\Wero\WeroGateway;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaCaptureAttempt;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Order\CaptureRecorder;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

class Test_KlarnaCaptureOperation extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableHpos();
        update_option('woocommerce_buckaroo_mastersettings_settings', [
            'culture' => 'en-US',
            'merchantkey' => 'test-merchant',
            'secretkey' => 'test-secret',
        ]);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];

        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }

        delete_option('woocommerce_buckaroo_mastersettings_settings');
        delete_option('woocommerce_buckaroo_wero_settings');
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_capture_uses_explicit_input_and_records_the_complete_klarna_pay(): void
    {
        $order = $this->createReservedOrder(12.50, 2);
        $item = current($order->get_items('line_item'));
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 2],
            [$item->get_id() => 25.00],
            [$item->get_id() => []]
        );
        $buckarooClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-123'));

        $_POST = [];
        $result = (new KlarnaPayGateway())->capture(
            $order,
            25.00,
            $allocation,
            $buckarooClient
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame('klarna', $buckarooClient->service);
        $this->assertSame('pay', $buckarooClient->action);
        $this->assertSame('25.00', $buckarooClient->payload['amountDebit']);
        $this->assertSame('DATA-REQUEST-123', $buckarooClient->payload['dataRequestKey']);
        $this->assertArrayNotHasKey('originalTransactionKey', $buckarooClient->payload);
        $this->assertSame(2, $buckarooClient->payload['articles'][0]['quantity']);
        $this->assertSame(12.50, $buckarooClient->payload['articles'][0]['price']);

        $captures = OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false);
        $this->assertCount(1, $captures);
        $this->assertSame('25.00', $captures[0]['amount']);
        $this->assertSame('EUR', $captures[0]['currency']);
        $this->assertSame('PAY-123', $captures[0]['transaction_id']);
        $this->assertSame(json_encode([$item->get_id() => 2]), $captures[0]['line_item_qtys']);
        $this->assertSame(json_encode([$item->get_id() => 25.00]), $captures[0]['line_item_totals']);
        $this->assertSame(json_encode([$item->get_id() => []]), $captures[0]['line_item_tax_totals']);
    }

    /**
     * @dataProvider billingCountryCultureProvider
     */
    public function test_capture_uses_the_billing_country_culture(
        string $country,
        string $culture,
        string $transactionKey
    ): void {
        $order = $this->createReservedOrder(12.50, 1);
        $order->set_billing_country($country);
        $order->save();
        $item = current($order->get_items('line_item'));
        $buckarooClient = new InMemoryBuckarooClient($this->successfulResponse($transactionKey));

        $result = (new KlarnaPayGateway())->capture(
            $order,
            12.50,
            CaptureAllocation::fromArrays(
                [$item->get_id() => 1],
                [$item->get_id() => 12.50],
                [$item->get_id() => []]
            ),
            $buckarooClient
        );

        $masterSettings = get_option('woocommerce_buckaroo_mastersettings_settings');
        $this->assertTrue($result->isSuccess());
        $this->assertSame('en-US', $masterSettings['culture']);
        $this->assertSame($culture, $buckarooClient->payload['culture']);
    }

    public static function billingCountryCultureProvider(): array
    {
        return [
            'Belgian billing address' => ['BE', 'nl-BE', 'PAY-BE'],
            'Dutch billing address' => ['NL', 'nl-NL', 'PAY-NL'],
        ];
    }

    public function test_capture_without_a_data_request_key_fails_before_transport(): void
    {
        $order = $this->createReservedOrder(12.50, 1);
        $item = current($order->get_items('line_item'));
        OrderMeta::delete($order, KlarnaProcessor::DATA_REQUEST_META_KEY);
        $buckarooClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-IGNORED'));

        $result = (new KlarnaPayGateway())->capture(
            wc_get_order($order->get_id()),
            12.50,
            CaptureAllocation::fromArrays(
                [$item->get_id() => 1],
                [$item->get_id() => 12.50],
                [$item->get_id() => []]
            ),
            $buckarooClient
        );

        $this->assertSame('failed', $result->getStatus());
        $this->assertStringContainsString('Data Request key', $result->getMessage());
        $this->assertSame(0, $buckarooClient->sendCount);
    }

    public function test_replayed_success_records_one_capture_and_refunds_against_it(): void
    {
        $order = $this->createReservedOrder(10.00, 1);
        $item = current($order->get_items('line_item'));
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 10.00],
            [$item->get_id() => []]
        );
        $buckarooClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-REFUND-SOURCE'));
        $gateway = new KlarnaPayGateway();

        $gateway->capture($order, 10.00, $allocation, $buckarooClient);
        $gateway->capture(wc_get_order($order->get_id()), 10.00, $allocation, $buckarooClient);

        $captures = OrderMeta::get($order, '_wc_order_captures', false);
        $refundBody = (new KlarnaPayGateway())
            ->newRefundProcessorInstance(wc_get_order($order->get_id()), 5.00, 'Partial refund')
            ->getBody();

        $this->assertCount(1, $captures);
        $this->assertSame('10.00', (string) OrderMeta::get($order, '_wc_order_amount_captured'));
        $this->assertSame('PAY-REFUND-SOURCE', $refundBody['originalTransactionKey']);
    }

    public function test_manual_capture_adapter_returns_the_existing_ajax_shape(): void
    {
        $order = $this->createReservedOrder(15.00, 1);
        $item = current($order->get_items('line_item'));
        $buckarooClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-AJAX'));
        $gateway = new TestableKlarnaPayGateway($buckarooClient);
        $_POST = [
            'capture_amount' => '15.00',
            'line_item_qtys' => wp_json_encode([$item->get_id() => 1]),
            'line_item_totals' => wp_json_encode([$item->get_id() => 15.00]),
            'line_item_tax_totals' => wp_json_encode([$item->get_id() => []]),
        ];

        $response = $gateway->process_capture($order->get_id());

        $this->assertTrue($response['success']);
        $this->assertSame(['Key' => 'PAY-AJAX'], $response['data']);
        $this->assertSame('15.00', $buckarooClient->payload['amountDebit']);
    }

    public function test_pending_capture_for_an_unrelated_gateway_is_not_reported_as_successful(): void
    {
        $order = $this->createReservedOrder(15.00, 1);
        $item = current($order->get_items('line_item'));
        $order->set_payment_method('buckaroo_klarnakp');
        $order->update_meta_data('_buckaroo_klarnakp_reservation_number', 'KP-RESERVATION');
        $order->save();
        $gateway = new TestableKlarnaKpGateway(
            new InMemoryBuckarooClient($this->pendingResponse('KP-PENDING'))
        );
        $_POST = [
            'capture_amount' => '15.00',
            'line_item_qtys' => wp_json_encode([$item->get_id() => 1]),
            'line_item_totals' => wp_json_encode([$item->get_id() => 15.00]),
            'line_item_tax_totals' => wp_json_encode([$item->get_id() => []]),
        ];

        $response = $gateway->process_capture($order->get_id());

        $this->assertArrayNotHasKey('success', $response);
        $this->assertArrayHasKey('errors', $response);
        $this->assertSame([], OrderMeta::get($order, '_wc_order_captures', false));
    }

    public function test_successful_capture_for_an_unrelated_gateway_preserves_its_payload_and_ledger(): void
    {
        update_option('woocommerce_buckaroo_wero_settings', ['weropayauthorize' => 'authorize']);
        $order = $this->createReservedOrder(15.00, 1);
        $item = current($order->get_items('line_item'));
        $order->set_payment_method('buckaroo_wero');
        $order->update_meta_data('_wc_order_authorized', 'yes');
        $order->save();
        $client = new InMemoryBuckarooClient($this->successfulResponse('WERO-CAPTURE'));
        $gateway = new TestableWeroGateway($client);
        $_POST = [
            'capture_amount' => '15.00',
            'line_item_qtys' => wp_json_encode([$item->get_id() => 1]),
            'line_item_totals' => wp_json_encode([$item->get_id() => 15.00]),
            'line_item_tax_totals' => wp_json_encode([$item->get_id() => []]),
        ];

        $response = $gateway->process_capture($order->get_id());

        $this->assertTrue($response['success']);
        $this->assertSame('15.00', $client->payload['amountDebit']);
        $this->assertSame('RESERVE-123', $client->payload['originalTransactionKey']);
        $this->assertArrayNotHasKey('articles', $client->payload);
        $captures = OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false);
        $this->assertCount(1, $captures);
        $this->assertSame('WERO-CAPTURE', $captures[0]['transaction_id']);
    }

    public function test_generic_capture_and_push_modules_do_not_contain_klarna_orchestration(): void
    {
        $genericFiles = [
            'src/Gateways/AbstractPaymentGateway.php',
            'src/Hooks/AdminHooks.php',
            'src/PaymentProcessors/Actions/CaptureAction.php',
            'src/PaymentProcessors/PushProcessor.php',
        ];

        foreach ($genericFiles as $genericFile) {
            $contents = file_get_contents(dirname(__DIR__) . '/' . $genericFile);
            $this->assertStringNotContainsString('Klarna', $contents, $genericFile);
            $this->assertStringNotContainsString('klarna', $contents, $genericFile);
        }

        $this->assertFileDoesNotExist(dirname(__DIR__) . '/src/Order/KlarnaCaptureAttempt.php');
        $this->assertFileExists(dirname(__DIR__) . '/src/Gateways/Klarna/KlarnaCaptureAttempt.php');
    }

    public function test_manual_capture_uses_the_same_remaining_allocation_as_automatic_capture(): void
    {
        $order = $this->createReservedOrder(12.50, 2);
        $item = current($order->get_items('line_item'));
        $gateway = new KlarnaPayGateway();
        $firstClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-PARTIAL'));
        $gateway->capture(
            $order,
            12.50,
            CaptureAllocation::fromArrays(
                [$item->get_id() => 1],
                [$item->get_id() => 12.50],
                [$item->get_id() => []]
            ),
            $firstClient
        );

        $overcaptureClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-OVER'));
        $overcapture = $gateway->capture(
            wc_get_order($order->get_id()),
            25.00,
            CaptureAllocation::fromArrays(
                [$item->get_id() => 2],
                [$item->get_id() => 25.00],
                [$item->get_id() => []]
            ),
            $overcaptureClient
        );

        $remainingClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-REMAINING'));
        $remaining = $gateway->capture(
            wc_get_order($order->get_id()),
            12.50,
            CaptureAllocation::fromArrays(
                [$item->get_id() => 1],
                [$item->get_id() => 12.50],
                [$item->get_id() => []]
            ),
            $remainingClient
        );

        $this->assertSame('failed', $overcapture->getStatus());
        $this->assertSame(0, $overcaptureClient->sendCount);
        $this->assertTrue($remaining->isSuccess());
        $captures = OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false);
        $this->assertCount(2, $captures);
        $refundBody = $gateway
            ->newRefundProcessorInstance(wc_get_order($order->get_id()), 5.00, 'Refund')
            ->getBody();
        $this->assertSame('PAY-REMAINING', $refundBody['originalTransactionKey']);
    }

    public function test_manual_capture_migrates_legacy_captures_before_calculating_remaining_capacity(): void
    {
        $order = $this->createReservedOrder(25.00, 1);
        $item = current($order->get_items('line_item'));
        add_post_meta($order->get_id(), '_wc_order_captures', [
            'id' => 'legacy-capture',
            'amount' => '25.00',
            'currency' => 'EUR',
            'line_item_qtys' => wp_json_encode([$item->get_id() => 1]),
            'line_item_totals' => wp_json_encode([$item->get_id() => 25.00]),
            'line_item_tax_totals' => wp_json_encode([$item->get_id() => []]),
            'transaction_id' => 'PAY-LEGACY',
        ]);
        $client = new InMemoryBuckarooClient($this->successfulResponse('PAY-OVER-CAPTURE'));

        $result = (new KlarnaPayGateway())->capture(
            $order,
            25.00,
            CaptureAllocation::fromArrays(
                [$item->get_id() => 1],
                [$item->get_id() => 25.00],
                [$item->get_id() => []]
            ),
            $client
        );

        $this->assertSame('failed', $result->getStatus());
        $this->assertSame(0, $client->sendCount);
        $this->assertCount(1, OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));
    }

    public function test_manual_pending_capture_claims_the_allocation_once_and_preserves_its_transaction_key(): void
    {
        $order = $this->createReservedOrder(25.00, 1);
        $item = current($order->get_items('line_item'));
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 25.00],
            [$item->get_id() => []]
        );
        $firstClient = new InMemoryBuckarooClient($this->pendingResponse('PAY-PENDING-MANUAL'));
        $secondClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-DUPLICATE'));
        $gateway = new KlarnaPayGateway();

        $first = $gateway->capture($order, 25.00, $allocation, $firstClient);
        $second = $gateway->capture(wc_get_order($order->get_id()), 25.00, $allocation, $secondClient);

        $attempts = KlarnaCaptureAttempt::all(wc_get_order($order->get_id()));
        $this->assertSame('pending', $first->getStatus());
        $this->assertSame('failed', $second->getStatus());
        $this->assertSame(1, $firstClient->sendCount);
        $this->assertSame(0, $secondClient->sendCount);
        $this->assertCount(1, $attempts);
        $this->assertSame('pending', $attempts[0]['state']);
        $this->assertSame('PAY-PENDING-MANUAL', $attempts[0]['transaction_key']);
    }

    public function test_manual_capture_rejects_a_different_allocation_that_partially_overlaps_a_pending_attempt(): void
    {
        $order = $this->createReservedOrder(10.00, 2);
        $item = current($order->get_items('line_item'));
        $gateway = new KlarnaPayGateway();
        $firstClient = new InMemoryBuckarooClient($this->pendingResponse('PAY-PARTIAL-PENDING'));
        $secondClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-OVERLAP'));

        $first = $gateway->capture(
            $order,
            10.00,
            CaptureAllocation::fromArrays(
                [$item->get_id() => 1],
                [$item->get_id() => 10.00],
                [$item->get_id() => []]
            ),
            $firstClient
        );
        $second = $gateway->capture(
            wc_get_order($order->get_id()),
            15.00,
            CaptureAllocation::fromArrays(
                [$item->get_id() => 1],
                [$item->get_id() => 15.00],
                [$item->get_id() => []]
            ),
            $secondClient
        );

        $this->assertSame('pending', $first->getStatus());
        $this->assertSame('failed', $second->getStatus());
        $this->assertSame(1, $firstClient->sendCount);
        $this->assertSame(0, $secondClient->sendCount);
        $this->assertCount(1, KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
    }

    public function test_failed_capture_result_and_attempt_keep_the_transaction_key(): void
    {
        $order = $this->createReservedOrder(25.00, 1);
        $item = current($order->get_items('line_item'));
        $client = new InMemoryBuckarooClient($this->failedResponse('PAY-FAILED-KNOWN'));

        $result = (new KlarnaPayGateway())->capture(
            $order,
            25.00,
            CaptureAllocation::fromArrays(
                [$item->get_id() => 1],
                [$item->get_id() => 25.00],
                [$item->get_id() => []]
            ),
            $client
        );

        $attempt = KlarnaCaptureAttempt::latest(wc_get_order($order->get_id()));
        $this->assertSame('failed', $result->getStatus());
        $this->assertSame('PAY-FAILED-KNOWN', $result->getTransactionKey());
        $this->assertSame('PAY-FAILED-KNOWN', $attempt['transaction_key']);
    }

    public function test_definite_manual_failure_releases_the_allocation_for_a_manual_retry(): void
    {
        $order = $this->createReservedOrder(25.00, 1);
        $item = current($order->get_items('line_item'));
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 25.00],
            [$item->get_id() => []]
        );
        $gateway = new KlarnaPayGateway();
        $failedClient = new InMemoryBuckarooClient($this->failedResponse('PAY-MANUAL-FAILED'));
        $successfulClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-MANUAL-RETRY'));

        $failed = $gateway->capture($order, 25.00, $allocation, $failedClient);
        $retried = $gateway->capture(
            wc_get_order($order->get_id()),
            25.00,
            $allocation,
            $successfulClient
        );

        $this->assertSame('failed', $failed->getStatus());
        $this->assertTrue($retried->isSuccess());
        $this->assertSame(1, $failedClient->sendCount);
        $this->assertSame(1, $successfulClient->sendCount);
        $this->assertCount(2, KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
    }

    public function test_successful_captures_with_different_keys_refresh_the_order_before_updating_the_aggregate(): void
    {
        $order = $this->createReservedOrder(10.00, 2);
        $item = current($order->get_items('line_item'));
        $firstOrder = wc_get_order($order->get_id());
        $staleOrder = wc_get_order($order->get_id());
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 10.00],
            [$item->get_id() => []]
        );

        CaptureRecorder::record($firstOrder, 10.00, 'EUR', $allocation, 'PAY-FIRST');
        CaptureRecorder::record($staleOrder, 10.00, 'EUR', $allocation, 'PAY-SECOND');

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('20', (string) OrderMeta::get($storedOrder, '_wc_order_amount_captured'));
        $this->assertCount(2, OrderMeta::get($storedOrder, '_wc_order_captures', false));
    }

    public function test_same_key_push_recorded_before_a_stale_worker_sync_is_not_duplicated(): void
    {
        $order = $this->createReservedOrder(10.00, 1);
        $item = current($order->get_items('line_item'));
        $pushOrder = wc_get_order($order->get_id());
        $staleWorkerOrder = wc_get_order($order->get_id());
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 10.00],
            [$item->get_id() => []]
        );

        CaptureRecorder::record($pushOrder, 10.00, 'EUR', $allocation, 'PAY-INTERLEAVED');
        CaptureRecorder::record($staleWorkerOrder, 10.00, 'EUR', $allocation, 'PAY-INTERLEAVED');

        $storedOrder = wc_get_order($order->get_id());
        $captures = OrderMeta::get($storedOrder, '_wc_order_captures', false);
        $this->assertCount(1, $captures);
        $this->assertSame('10.00', (string) OrderMeta::get($storedOrder, '_wc_order_amount_captured'));
    }

    private function createReservedOrder(float $unitPrice, int $quantity): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Capture product');
        $product->set_regular_price((string) $unitPrice);
        $product->set_price((string) $unitPrice);
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, $quantity);
        $order->set_payment_method('buckaroo_klarnapay');
        $order->set_currency('EUR');
        $order->set_transaction_id('RESERVE-123');
        $order->calculate_totals();
        $order->update_meta_data('buckaroo_is_reserved', 'yes');
        $order->update_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY, 'DATA-REQUEST-123');
        $order->save();

        return wc_get_order($order->get_id());
    }

    private function successfulResponse(string $transactionKey): TransactionResponse
    {
        $response = $this->getMockBuilder(TransactionResponse::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSuccess', 'isPendingProcessing', 'getStatusCode', 'getTransactionKey', 'toArray'])
            ->getMock();
        $response->method('isSuccess')->willReturn(true);
        $response->method('isPendingProcessing')->willReturn(false);
        $response->method('getStatusCode')->willReturn(190);
        $response->method('getTransactionKey')->willReturn($transactionKey);
        $response->method('toArray')->willReturn(['Key' => $transactionKey]);

        return $response;
    }

    private function pendingResponse(string $transactionKey): TransactionResponse
    {
        $response = $this->getMockBuilder(TransactionResponse::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSuccess', 'isPendingProcessing', 'getStatusCode', 'getTransactionKey', 'toArray'])
            ->getMock();
        $response->method('isSuccess')->willReturn(false);
        $response->method('isPendingProcessing')->willReturn(true);
        $response->method('getStatusCode')->willReturn(791);
        $response->method('getTransactionKey')->willReturn($transactionKey);
        $response->method('toArray')->willReturn(['Key' => $transactionKey]);

        return $response;
    }

    private function failedResponse(string $transactionKey): TransactionResponse
    {
        $response = $this->getMockBuilder(TransactionResponse::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSuccess', 'isPendingProcessing', 'getStatusCode', 'getTransactionKey', 'getSomeError', 'toArray'])
            ->getMock();
        $response->method('isSuccess')->willReturn(false);
        $response->method('isPendingProcessing')->willReturn(false);
        $response->method('getStatusCode')->willReturn(490);
        $response->method('getTransactionKey')->willReturn($transactionKey);
        $response->method('getSomeError')->willReturn('Capture declined');
        $response->method('toArray')->willReturn(['Key' => $transactionKey]);

        return $response;
    }
}

class TestableKlarnaPayGateway extends KlarnaPayGateway
{
    /** @var BuckarooClient */
    private $captureClient;

    public function __construct(BuckarooClient $captureClient)
    {
        $this->captureClient = $captureClient;
        parent::__construct();
    }

    protected function executeCapture(
        WC_Order $order,
        $amount,
        CaptureAllocation $allocation,
        ?BuckarooClient $buckarooClient = null,
        ?int $attemptNumber = null
    ): CaptureResult {
        return parent::executeCapture($order, $amount, $allocation, $this->captureClient);
    }
}

class TestableKlarnaKpGateway extends KlarnaKpGateway
{
    /** @var BuckarooClient */
    private $captureClient;

    public function __construct(BuckarooClient $captureClient)
    {
        $this->captureClient = $captureClient;
        parent::__construct();
    }

    protected function executeCapture(
        WC_Order $order,
        $amount,
        CaptureAllocation $allocation,
        ?BuckarooClient $buckarooClient = null,
        ?int $attemptNumber = null
    ): CaptureResult {
        return parent::executeCapture($order, $amount, $allocation, $this->captureClient);
    }
}

class TestableWeroGateway extends WeroGateway
{
    /** @var BuckarooClient */
    private $captureClient;

    public function __construct(BuckarooClient $captureClient)
    {
        $this->captureClient = $captureClient;
        parent::__construct();
    }

    protected function executeCapture(
        WC_Order $order,
        $amount,
        CaptureAllocation $allocation,
        ?BuckarooClient $buckarooClient = null,
        ?int $attemptNumber = null
    ): CaptureResult {
        return parent::executeCapture($order, $amount, $allocation, $this->captureClient);
    }
}
