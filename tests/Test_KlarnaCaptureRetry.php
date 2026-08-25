<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaFulfillmentActions;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Hooks\AdminHooks;
use Buckaroo\Woocommerce\Order\KlarnaCaptureAttempt;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

class Test_KlarnaCaptureRetry extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    /** @var int[] */
    private $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableHpos();
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        as_unschedule_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK);
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        wp_set_current_user($this->createUser('administrator'));
    }

    protected function tearDown(): void
    {
        as_unschedule_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK);
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        delete_option('woocommerce_buckaroo_klarnapay_settings');
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        delete_option(KlarnaCaptureAttempt::NOTIFICATIONS_OPTION);
        wp_set_current_user(0);

        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }

        foreach ($this->userIds as $userId) {
            wp_delete_user($userId);
        }

        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_definite_failure_is_visible_and_retryable_only_to_an_authorized_manager(): void
    {
        $order = $this->createReservedOrder();
        $buckarooClient = new InMemoryBuckarooClient($this->failedResponse('Capture declined'));
        $actions = new KlarnaFulfillmentActions($buckarooClient);
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);

        $storedOrder = wc_get_order($order->get_id());
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $automaticFailureNotes = array_values(array_filter(
            $notes,
            static function ($note) {
                return strpos($note->content, 'Automatic Klarna capture') !== false;
            }
        ));
        $this->assertCount(1, $automaticFailureNotes);
        $this->assertStringContainsString('25.00 EUR', $automaticFailureNotes[0]->content);
        $this->assertStringContainsString('attempt 1', $automaticFailureNotes[0]->content);
        $this->assertStringContainsString('Capture declined', $automaticFailureNotes[0]->content);
        $this->assertSame('completed', $storedOrder->get_status());

        $availableActions = $actions->add_fulfillment_actions([], $storedOrder);
        $this->assertArrayHasKey('buckaroo_klarnapay_retry_capture', $availableActions);

        ob_start();
        (new AdminHooks())->handleNotices();
        $notice = (string) ob_get_clean();
        $this->assertStringContainsString((string) $order->get_id(), $notice);
        $this->assertStringContainsString('Capture declined', $notice);

        wp_set_current_user($this->createUser('subscriber'));
        $this->assertArrayNotHasKey(
            'buckaroo_klarnapay_retry_capture',
            $actions->add_fulfillment_actions([], $storedOrder)
        );
        $actions->handle_retry_capture($storedOrder);
        $this->assertCount(1, KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
    }

    public function test_repeated_retry_requests_queue_one_audited_attempt_and_success_clears_the_failure(): void
    {
        $order = $this->createReservedOrder();
        $failedClient = new InMemoryBuckarooClient($this->failedResponse('Temporary decline'));
        $actions = new KlarnaFulfillmentActions($failedClient);
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);
        $storedOrder = wc_get_order($order->get_id());

        $actions->handle_retry_capture($storedOrder);
        $actions->handle_retry_capture($storedOrder);

        $attempts = KlarnaCaptureAttempt::all(wc_get_order($order->get_id()));
        $this->assertCount(2, $attempts);
        $this->assertSame('failed', $attempts[0]['state']);
        $this->assertSame('queued', $attempts[1]['state']);
        $this->assertTrue((bool) as_has_scheduled_action(
            KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK,
            [$order->get_id(), 2],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));

        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        $successfulClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-RETRY'));
        $successfulActions = new KlarnaFulfillmentActions($successfulClient);
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 2);

        $storedOrder = wc_get_order($order->get_id());
        $attempts = KlarnaCaptureAttempt::all($storedOrder);
        $this->assertSame('succeeded', $attempts[1]['state']);
        $this->assertSame(1, $successfulClient->sendCount);
        $this->assertArrayNotHasKey($order->get_id(), KlarnaCaptureAttempt::notifications());
        $this->assertArrayNotHasKey(
            'buckaroo_klarnapay_retry_capture',
            $successfulActions->add_fulfillment_actions([], $storedOrder)
        );
        $this->assertCount(1, OrderMeta::get($storedOrder, '_wc_order_captures', false));
        $this->assertSame('completed', $storedOrder->get_status());
    }

    public function test_unknown_outcome_is_visible_but_cannot_be_retried(): void
    {
        $order = $this->createReservedOrder();
        $buckarooClient = $this->getMockBuilder(BuckarooClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['process'])
            ->getMock();
        $buckarooClient->method('process')->willThrowException(new RuntimeException('Connection lost'));
        $actions = new KlarnaFulfillmentActions($buckarooClient);
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('unknown', KlarnaCaptureAttempt::latest($storedOrder)['state']);
        $this->assertArrayNotHasKey(
            'buckaroo_klarnapay_retry_capture',
            $actions->add_fulfillment_actions([], $storedOrder)
        );
        $actions->handle_retry_capture($storedOrder);
        $this->assertCount(1, KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));

        ob_start();
        (new AdminHooks())->handleNotices();
        $notice = (string) ob_get_clean();
        $this->assertStringContainsString('outcome is unknown', $notice);
        $this->assertStringContainsString('Connection lost', $notice);
    }

    private function createReservedOrder(): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Retry product');
        $product->set_regular_price('25.00');
        $product->set_price('25.00');
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, 1);
        $order->set_payment_method('buckaroo_klarnapay');
        $order->set_currency('EUR');
        $order->calculate_totals();
        $order->update_meta_data('buckaroo_is_reserved', 'yes');
        $order->update_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY, 'DATA-REQUEST-RETRY');
        $order->update_meta_data(KlarnaProcessor::RESERVED_AMOUNT_META_KEY, '25.00');
        $order->save();

        return wc_get_order($order->get_id());
    }

    private function failedResponse(string $error): TransactionResponse
    {
        $response = $this->getMockBuilder(TransactionResponse::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'isSuccess',
                'isPendingProcessing',
                'getStatusCode',
                'getTransactionKey',
                'getSomeError',
                'toArray',
            ])
            ->getMock();
        $response->method('isSuccess')->willReturn(false);
        $response->method('isPendingProcessing')->willReturn(false);
        $response->method('getStatusCode')->willReturn(490);
        $response->method('getTransactionKey')->willReturn('PAY-FAILED');
        $response->method('getSomeError')->willReturn($error);
        $response->method('toArray')->willReturn(['Key' => 'PAY-FAILED']);

        return $response;
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

    private function createUser(string $role): int
    {
        $userId = wp_create_user(
            uniqid('capture-', true),
            'password',
            uniqid('capture-', true) . '@example.com'
        );
        $user = get_user_by('id', $userId);
        $user->set_role($role);
        $this->userIds[] = $userId;

        return $userId;
    }
}
