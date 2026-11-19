<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\CaptureRecorder;
use Buckaroo\Woocommerce\Order\OrderCaptureRefund;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Order\RefundLedger;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\RefundAction;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\ResponseParser\FormDataParser;
use Buckaroo\Woocommerce\Services\Request;
use PHPUnit\Framework\TestCase;

/**
 * Refunds of captured orders: from the order screen, from the capture
 * meta box and from Buckaroo pushes.
 */
class Test_CaptureRefunds extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    /** @var OtherConnectionLock|null */
    private $otherConnection;

    /** @var array|null */
    private $originalGateways;

    protected function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_buckaroo_mastersettings_settings', [
            'culture' => 'en-US',
            'merchantkey' => 'test-merchant',
            'secretkey' => 'test-secret',
        ]);
        update_option('woocommerce_buckaroo_creditcard_settings', ['creditcardpayauthorize' => 'authorize']);
    }

    protected function tearDown(): void
    {
        if ($this->otherConnection !== null) {
            $this->otherConnection->close();
            $this->otherConnection = null;
        }

        if ($this->originalGateways !== null) {
            WC()->payment_gateways()->payment_gateways = $this->originalGateways;
            $this->originalGateways = null;
        }

        $_POST = [];

        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }

        delete_option('woocommerce_buckaroo_mastersettings_settings');
        delete_option('woocommerce_buckaroo_creditcard_settings');
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public static function storages(): array
    {
        return ['hpos' => [true], 'legacy' => [false]];
    }

    /**
     * @dataProvider storages
     */
    public function test_a_fully_refunded_capture_is_not_refunded_again(bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(
            RecordingBuckarooClient::response(190, 'REFUND1', '40.00'),
            RecordingBuckarooClient::response(190, 'REFUND2', '40.00')
        );

        $first = $this->refund($order, 40.00, $client, 'CAPTURE1');
        $second = $this->refund($order, 40.00, $client, 'CAPTURE1');

        $this->assertTrue($first);
        $this->assertInstanceOf(WP_Error::class, $second);
        $this->assertSame(1, $client->sendCount);
        $this->assertSame('CAPTURE1', $client->requests[0]['originalTransactionKey']);
    }

    /**
     * @dataProvider storages
     */
    public function test_partial_refunds_are_allowed_up_to_the_remaining_capture_amount(bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(
            RecordingBuckarooClient::response(190, 'REFUND1', '15.00'),
            RecordingBuckarooClient::response(190, 'REFUND2', '25.00')
        );

        $this->assertTrue($this->refund($order, 15.00, $client));
        $this->assertTrue($this->refund($order, 25.00, $client));
        $this->assertInstanceOf(WP_Error::class, $this->refund($order, 0.01, $client));

        $this->assertSame(2, $client->sendCount);
        $this->assertSame(0, RefundLedger::remainingCaptureCents(wc_get_order($order->get_id()), 'CAPTURE1'));
    }

    public function test_refund_above_the_remaining_capture_amount_is_not_sent(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'REFUND1', '30.00'));

        $this->assertTrue($this->refund($order, 30.00, $client));
        $result = $this->refund($order, 10.01, $client);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(1, $client->sendCount);
    }

    public function test_refunds_are_sent_against_the_capture_that_has_the_amount_left(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([15.00, 25.00]);
        $client = new RecordingBuckarooClient(
            RecordingBuckarooClient::response(190, 'REFUND1', '20.00'),
            RecordingBuckarooClient::response(190, 'REFUND2', '15.00'),
            RecordingBuckarooClient::response(190, 'REFUND3', '5.00')
        );

        $this->assertTrue($this->refund($order, 20.00, $client));
        $this->assertTrue($this->refund($order, 15.00, $client));
        $this->assertTrue($this->refund($order, 5.00, $client));

        $this->assertSame(
            ['CAPTURE2', 'CAPTURE1', 'CAPTURE2'],
            array_column($client->requests, 'originalTransactionKey')
        );
        $stored = wc_get_order($order->get_id());
        $this->assertSame(0, RefundLedger::remainingCaptureCents($stored, 'CAPTURE1'));
        $this->assertSame(0, RefundLedger::remainingCaptureCents($stored, 'CAPTURE2'));
    }

    public function test_refund_larger_than_any_single_capture_is_not_sent(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([15.00, 25.00]);
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'REFUND1', '30.00'));

        $result = $this->refund($order, 30.00, $client);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertStringContainsString('single capture', $result->get_error_message());
        $this->assertSame(0, $client->sendCount);
    }

    public function test_refund_is_not_sent_while_another_request_updates_the_order(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $this->otherConnection = new OtherConnectionLock();
        $this->assertTrue($this->otherConnection->acquire(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $order, PushProcessor::SETTLEMENT_LOCK_KEY));
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'REFUND1', '40.00'));

        $result = $this->refund($order, 40.00, $client);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(0, $client->sendCount);
        $this->assertSame([], RefundLedger::entries(wc_get_order($order->get_id())));
    }

    public function test_a_second_admin_cannot_refund_the_amount_the_first_admin_refunded(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(
            RecordingBuckarooClient::response(190, 'REFUND1', '40.00'),
            RecordingBuckarooClient::response(190, 'REFUND2', '40.00')
        );
        // Both admins loaded the order before either refund was sent.
        $firstAdmin = wc_get_order($order->get_id());
        $secondAdmin = wc_get_order($order->get_id());

        $this->assertTrue($this->refund($firstAdmin, 40.00, $client));
        $this->assertInstanceOf(WP_Error::class, $this->refund($secondAdmin, 40.00, $client));
        $this->assertSame(1, $client->sendCount);
    }

    public function test_a_declined_refund_releases_the_reserved_amount(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(
            RecordingBuckarooClient::response(490, 'REFUND1', '40.00'),
            RecordingBuckarooClient::response(190, 'REFUND2', '40.00')
        );

        $this->assertInstanceOf(WP_Error::class, $this->refund($order, 40.00, $client));
        $this->assertSame([], RefundLedger::entries(wc_get_order($order->get_id())));
        $this->assertTrue($this->refund($order, 40.00, $client));
        $this->assertSame(2, $client->sendCount);
    }

    /**
     * @dataProvider storages
     */
    public function test_refund_without_a_result_keeps_its_amount_reserved_until_the_push(bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(new RuntimeException('Timeout'));

        $result = $this->refund($order, 40.00, $client);
        $retry = $this->refund($order, 40.00, $client);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertInstanceOf(WP_Error::class, $retry);
        $this->assertSame(1, $client->sendCount);

        $this->push($order, 190, 'REFUND1', 'CAPTURE1', '40.00');
        $this->push($order, 190, 'REFUND1', 'CAPTURE1', '40.00');

        $stored = wc_get_order($order->get_id());
        $this->assertCount(1, $stored->get_refunds());
        $this->assertSame('40.00', wc_format_decimal($stored->get_total_refunded(), 2));
        $entries = array_values(RefundLedger::entries($stored));
        $this->assertCount(1, $entries);
        $this->assertSame(RefundLedger::STATE_COMPLETED, $entries[0]['state']);
        $this->assertSame('REFUND1', $entries[0]['refund_key']);
    }

    public function test_a_push_cannot_be_recorded_while_the_refund_is_being_sent(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $this->otherConnection = new OtherConnectionLock();
        $lockedDuringSend = null;
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'REFUND1', '40.00'));
        $client->onSend = function () use ($order, &$lockedDuringSend) {
            $lockedDuringSend = ! $this->otherConnection->isFree(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $order, PushProcessor::SETTLEMENT_LOCK_KEY);
        };

        $this->assertTrue($this->refund($order, 40.00, $client));
        // The push waited for the lock and is handled after the response.
        $this->push($order, 190, 'REFUND1', 'CAPTURE1', '40.00');

        $this->assertTrue($lockedDuringSend);
        $this->assertCount(0, wc_get_order($order->get_id())->get_refunds());
    }

    public function test_a_pending_refund_is_completed_by_its_push_without_another_refund(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(791, 'REFUND1', '40.00'));

        $this->assertTrue($this->refund($order, 40.00, $client));
        $entry = current(RefundLedger::entries(wc_get_order($order->get_id())));
        $this->assertSame(RefundLedger::STATE_PENDING, $entry['state']);

        $this->push($order, 190, 'REFUND1', 'CAPTURE1', '40.00');

        $stored = wc_get_order($order->get_id());
        $entry = current(RefundLedger::entries($stored));
        $this->assertSame(RefundLedger::STATE_COMPLETED, $entry['state']);
        $this->assertCount(0, $stored->get_refunds());
    }

    /**
     * @dataProvider storages
     */
    public function test_a_refund_made_in_buckaroo_is_recorded_once(bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createCapturedOrder([40.00]);

        $this->push($order, 190, 'PLAZAREFUND1', 'CAPTURE1', '12.50');
        $this->push($order, 190, 'PLAZAREFUND1', 'CAPTURE1', '12.50');

        $stored = wc_get_order($order->get_id());
        $this->assertCount(1, $stored->get_refunds());
        $this->assertSame('12.50', wc_format_decimal($stored->get_total_refunded(), 2));
        $this->assertSame(2750, RefundLedger::remainingCaptureCents($stored, 'CAPTURE1'));
    }

    /**
     * @dataProvider unfinishedStatuses
     */
    public function test_an_unfinished_refund_push_records_no_refund(int $statusCode): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);

        $this->push($order, $statusCode, 'PLAZAREFUND1', 'CAPTURE1', '12.50');

        $stored = wc_get_order($order->get_id());
        $this->assertCount(0, $stored->get_refunds());
        $this->assertSame([], RefundLedger::entries($stored));
    }

    public static function unfinishedStatuses(): array
    {
        return [
            'pending processing' => [791],
            'waiting on consumer' => [792],
            'on hold' => [793],
            'failed' => [490],
        ];
    }

    public function test_a_refund_push_for_a_transaction_of_another_order_records_no_refund(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $otherOrder = $this->createCapturedOrder([40.00], 'OTHER');

        $this->push($order, 190, 'PLAZAREFUND1', 'OTHERCAPTURE1', '12.50');

        $this->assertCount(0, wc_get_order($order->get_id())->get_refunds());
        $this->assertCount(0, wc_get_order($otherOrder->get_id())->get_refunds());
    }

    /**
     * @dataProvider invalidAmounts
     */
    public function test_a_refund_push_without_a_valid_amount_records_no_refund(?string $amount): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);

        $this->push($order, 190, 'PLAZAREFUND1', 'CAPTURE1', $amount);

        $this->assertCount(0, wc_get_order($order->get_id())->get_refunds());
    }

    public static function invalidAmounts(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'not a number' => ['abc'],
            'negative' => ['-5.00'],
            'zero' => ['0.00'],
        ];
    }

    public function test_a_refund_push_above_the_remaining_capture_amount_records_no_refund(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([15.00, 25.00]);

        $this->push($order, 190, 'PLAZAREFUND1', 'CAPTURE1', '20.00');

        $this->assertCount(0, wc_get_order($order->get_id())->get_refunds());
    }

    /**
     * @dataProvider storages
     */
    public function test_capture_refund_from_the_meta_box_creates_one_refund_and_one_request(bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'REFUND1', '40.00'));
        $this->useGatewayClient($client);

        $first = $this->refundCapture($order, 'CAPTURE1');
        $second = $this->refundCapture(wc_get_order($order->get_id()), 'CAPTURE1');

        $this->assertInstanceOf(WC_Order_Refund::class, $first);
        $this->assertInstanceOf(WP_Error::class, $second);
        $this->assertSame(1, $client->sendCount);
        $this->assertSame('CAPTURE1', $client->requests[0]['originalTransactionKey']);
        $stored = wc_get_order($order->get_id());
        $this->assertCount(1, $stored->get_refunds());
        $this->assertTrue(current($stored->get_refunds())->get_refunded_payment());
    }

    public function test_the_refund_is_linked_to_the_woocommerce_refund_created_in_the_same_request(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([20.00, 20.00]);
        $own = wc_create_refund(['amount' => '20.00', 'order_id' => $order->get_id(), 'refund_payment' => false]);
        // Another request's refund of the same amount, created later.
        $other = wc_create_refund(['amount' => '20.00', 'order_id' => $order->get_id(), 'refund_payment' => false]);
        $this->assertGreaterThan($own->get_id(), $other->get_id());
        RefundAction::rememberCreatedRefund($own);
        $client = new RecordingBuckarooClient(new RuntimeException('Timeout'));

        $this->refund($order, 20.00, $client);

        $entry = current(RefundLedger::entries(wc_get_order($order->get_id())));
        $this->assertSame($own->get_id(), $entry['wc_refund_id']);
    }

    public function test_capture_refund_from_the_meta_box_links_its_own_woocommerce_refund(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'REFUND1', '40.00'));
        $this->useGatewayClient($client);

        $refund = $this->refundCapture($order, 'CAPTURE1');

        $this->assertInstanceOf(WC_Order_Refund::class, $refund);
        $entry = current(RefundLedger::entries(wc_get_order($order->get_id())));
        $this->assertSame($refund->get_id(), $entry['wc_refund_id']);
    }

    public function test_capture_refund_is_not_sent_when_the_woocommerce_refund_cannot_be_created(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'REFUND1', '40.00'));
        $this->useGatewayClient($client);
        // The order was refunded in WooCommerce only, so no amount is left.
        wc_create_refund(['amount' => '40.00', 'order_id' => $order->get_id(), 'refund_payment' => false]);

        $result = $this->refundCapture(wc_get_order($order->get_id()), 'CAPTURE1');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(0, $client->sendCount);
    }

    public function test_a_declined_capture_refund_leaves_no_woocommerce_refund(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(490, 'REFUND1', '40.00'));
        $this->useGatewayClient($client);

        $result = $this->refundCapture($order, 'CAPTURE1');

        $stored = wc_get_order($order->get_id());
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(1, $client->sendCount);
        $this->assertCount(0, $stored->get_refunds());
        $this->assertSame([], RefundLedger::entries($stored));
    }

    public function test_capture_refund_request_for_an_order_the_user_may_not_edit_is_rejected(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturedOrder([40.00]);
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'REFUND1', '40.00'));
        $this->useGatewayClient($client);
        $capture = current(OrderMeta::get($order, '_wc_order_captures', false));
        $userId = wp_insert_user([
            'user_login' => 'bk_editor_' . wp_generate_password(6, false),
            'user_pass' => wp_generate_password(),
            'role' => 'editor',
        ]);
        get_user_by('id', $userId)->add_cap('edit_shop_orders');
        wp_set_current_user($userId);
        // WordPress maps edit_shop_order to edit_post before this filter runs.
        $deny = static function ($caps, $cap, $user, $args) use ($order) {
            return in_array($cap, ['edit_shop_order', 'edit_post'], true) && (int) ($args[0] ?? 0) === $order->get_id()
                ? ['do_not_allow']
                : $caps;
        };
        $die = static function () {
            return static function () {
                throw new RuntimeException('wp_die');
            };
        };
        add_filter('map_meta_cap', $deny, 10, 4);
        add_filter('wp_die_handler', $die);
        add_filter('wp_die_ajax_handler', $die);
        add_filter('wp_doing_ajax', '__return_true');
        $_POST = ['order_id' => (string) $order->get_id(), 'capture_id' => $capture['id']];
        $_REQUEST = ['security' => wp_create_nonce('order-item')];
        ob_start();
        try {
            (new OrderCaptureRefund())->refund_capture();
        } catch (RuntimeException $exception) {
            // wp_send_json ends the request
        } finally {
            $output = ob_get_clean();
            remove_filter('map_meta_cap', $deny, 10);
            remove_filter('wp_die_handler', $die);
            remove_filter('wp_die_ajax_handler', $die);
            remove_filter('wp_doing_ajax', '__return_true');
            $_REQUEST = [];
            wp_set_current_user(0);
            wp_delete_user($userId);
        }

        $response = json_decode((string) $output, true);
        $this->assertSame('You are not allowed to refund this order.', $response['error'] ?? null);
        $this->assertSame(0, $client->sendCount);
        $this->assertCount(0, wc_get_order($order->get_id())->get_refunds());
    }

    /**
     * @return true|WP_Error
     */
    private function refund(WC_Order $order, float $amount, RecordingBuckarooClient $client, ?string $captureKey = null)
    {
        $gateway = new CreditCardGateway();

        return (new RefundAction(
            $gateway->newRefundProcessorInstance($order, $amount, ''),
            $order->get_id(),
            $captureKey,
            $client
        ))->process();
    }

    private function push(WC_Order $order, int $statusCode, string $refundKey, string $parentKey, ?string $amount): void
    {
        $fields = [
            'brq_statuscode' => (string) $statusCode,
            'brq_transactions' => $refundKey,
            'brq_relatedtransaction_refund' => $parentKey,
            'brq_currency' => 'EUR',
        ];
        if ($amount !== null) {
            $fields['brq_amount_credit'] = $amount;
        }

        RefundAction::handleRefundPush(wc_get_order($order->get_id()), new FormDataParser($fields));
    }

    /**
     * @return WC_Order_Refund|WP_Error
     */
    private function refundCapture(WC_Order $order, string $captureKey)
    {
        $capture = null;
        foreach (OrderMeta::get($order, '_wc_order_captures', false) as $stored) {
            if (($stored['transaction_id'] ?? null) === $captureKey) {
                $capture = $stored;
            }
        }

        $method = new ReflectionMethod(OrderCaptureRefund::class, 'refund_capture_in_woocommerce');
        $method->setAccessible(true);
        $_POST = ['reason' => '', 'restock' => 'false'];

        return $method->invoke(new OrderCaptureRefund(), new Request(), $order, $capture);
    }

    /** WooCommerce calls process_refund on its own gateway instance. */
    private function useGatewayClient(RecordingBuckarooClient $client): void
    {
        $gateways = WC()->payment_gateways();
        $this->originalGateways = $gateways->payment_gateways;
        $gateways->payment_gateways['buckaroo_creditcard'] = new class ($client) extends CreditCardGateway {
            /** @var RecordingBuckarooClient */
            private $client;

            public function __construct(RecordingBuckarooClient $client)
            {
                $this->client = $client;
                parent::__construct();
            }

            public function process_refund($order_id, $amount = null, $reason = '', $transactionId = null)
            {
                return (new RefundAction(
                    $this->newRefundProcessorInstance($order_id, $amount, $reason),
                    $order_id,
                    $transactionId,
                    $this->client
                ))->process();
            }
        };
    }

    private function useStorage(bool $hpos): void
    {
        if ($hpos) {
            $this->enableHpos();
        } else {
            $this->disableHpos();
        }
    }

    /**
     * An order of 2 x 20.00, captured in the given parts. The captures have
     * the keys <prefix>CAPTURE1, <prefix>CAPTURE2, ...
     *
     * @param  float[]  $captures
     */
    private function createCapturedOrder(array $captures, string $prefix = ''): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Refund product');
        $product->set_regular_price('20');
        $product->set_price('20');
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, 2);
        $order->set_payment_method('buckaroo_creditcard');
        $order->set_currency('EUR');
        $order->set_transaction_id($prefix . 'AUTHORIZE1');
        $order->calculate_totals(false);
        $order->update_meta_data('_wc_order_authorized', 'yes');
        $order->update_meta_data('_pushallowed', 'ok');
        $order->set_status('processing');
        $order->save();

        $item = current($order->get_items('line_item'));
        foreach ($captures as $index => $amount) {
            $quantity = (int) round($amount / 20);
            $result = CaptureRecorder::record(
                wc_get_order($order->get_id()),
                $amount,
                'EUR',
                CaptureAllocation::fromArrays([$item->get_id() => $quantity], [$item->get_id() => $amount], []),
                $prefix . 'CAPTURE' . ($index + 1)
            );
            $this->assertTrue($result->isSuccess(), $result->getMessage());
        }

        return wc_get_order($order->get_id());
    }
}
