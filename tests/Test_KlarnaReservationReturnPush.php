<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaFulfillmentActions;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPushProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\PaymentProcessors\ReturnProcessor;
use Buckaroo\Woocommerce\ResponseParser\FormDataParser;
use PHPUnit\Framework\TestCase;

/**
 * The browser return and the reservation push of a Klarna (MoR) reserve can arrive
 * in any order; only the push may move the order to its authorized (processing) state,
 * and a reservation must never be recorded as captured.
 */
class Test_KlarnaReservationReturnPush extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        $this->enableHpos();
        update_option('woocommerce_buckaroo_mastersettings_settings', [
            'culture' => 'en-US',
            'merchantkey' => 'test-store-key',
            'secretkey' => 'test-secret-key',
        ]);
        new KlarnaFulfillmentActions();
    }

    protected function tearDown(): void
    {
        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }

        delete_option('woocommerce_buckaroo_mastersettings_settings');
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_return_before_reservation_push_leaves_the_order_authorized_in_processing(): void
    {
        $order = $this->createKlarnaOrder();

        $this->browserReturn($order, '190');
        $this->reservationPush($order);

        $this->assertAuthorizedInProcessing(wc_get_order($order->get_id()));
    }

    public function test_reservation_push_before_return_leaves_the_order_authorized_in_processing(): void
    {
        $order = $this->createKlarnaOrder();

        $this->reservationPush($order);
        $this->browserReturn(wc_get_order($order->get_id()), '190');

        $this->assertAuthorizedInProcessing(wc_get_order($order->get_id()));
    }

    public function test_the_browser_return_does_not_write_the_reservation_state(): void
    {
        foreach (['190', '791'] as $statusCode) {
            $order = $this->createKlarnaOrder();

            $this->browserReturn($order, $statusCode);

            $stored = wc_get_order($order->get_id());
            $this->assertSame('', $stored->get_meta('buckaroo_is_reserved'), 'Status ' . $statusCode);
            $this->assertSame('', $stored->get_meta(KlarnaProcessor::RESERVED_AMOUNT_META_KEY), 'Status ' . $statusCode);
            $this->assertSame('', $stored->get_meta(KlarnaPushProcessor::RESERVATION_PUSH_META_KEY), 'Status ' . $statusCode);
            $this->assertSame('DATA-REQUEST-KEY', $stored->get_meta(KlarnaProcessor::DATA_REQUEST_META_KEY));
            $this->assertSame('pending', $stored->get_status());
        }
    }

    public function test_a_replayed_reservation_push_does_not_change_the_order_again(): void
    {
        $order = $this->createKlarnaOrder();

        $this->reservationPush($order);
        $this->reservationPush(wc_get_order($order->get_id()));

        $this->assertAuthorizedInProcessing(wc_get_order($order->get_id()));
    }

    public function test_a_capture_push_after_the_reservation_still_completes_the_order(): void
    {
        $order = $this->createKlarnaOrder();

        $this->browserReturn($order, '190');
        $this->reservationPush($order);
        $this->push(wc_get_order($order->get_id()), [
            'brq_action' => 'Pay',
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'CAPTURE-PUSH',
            'brq_transaction_method' => 'klarna',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);

        $stored = wc_get_order($order->get_id());
        $this->assertTrue($stored->is_paid(), 'Order should be paid, got status ' . $stored->get_status());
    }

    public function test_the_reservation_is_stored_before_the_status_hooks_run(): void
    {
        $order = $this->createKlarnaOrder();
        $seen = [];
        $listener = function ($orderId) use (&$seen) {
            $stored = wc_get_order($orderId);
            $seen = [
                'reserved' => $stored->get_meta('buckaroo_is_reserved'),
                'amount' => $stored->get_meta(KlarnaProcessor::RESERVED_AMOUNT_META_KEY),
                'data_request' => $stored->get_meta(KlarnaProcessor::DATA_REQUEST_META_KEY),
            ];
        };
        add_action('woocommerce_order_status_processing', $listener);

        try {
            $this->reservationPush($order);
        } finally {
            remove_action('woocommerce_order_status_processing', $listener);
        }

        $this->assertSame(
            ['reserved' => 'yes', 'amount' => '25.00', 'data_request' => 'DATA-REQUEST-KEY'],
            $seen
        );
    }

    public function test_a_reservation_push_with_a_zero_amount_reserves_the_order_total(): void
    {
        $order = $this->createKlarnaOrder();

        $this->push($order, [
            'brq_action' => 'Reserve',
            'brq_statuscode' => '190',
            'brq_amount' => '0.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'RESERVE-PUSH',
            'brq_transaction_method' => 'klarna',
            'brq_datarequest' => 'DATA-REQUEST-KEY',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);

        $this->assertSame('25.00', wc_get_order($order->get_id())->get_meta(KlarnaProcessor::RESERVED_AMOUNT_META_KEY));
    }

    public function test_a_capture_push_without_pay_action_is_not_handled_as_the_reservation(): void
    {
        $order = $this->createKlarnaOrder();
        $push = new FormDataParser([
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PLAZA-CAPTURE',
            'brq_transaction_type' => 'C000',
            'brq_transaction_method' => 'klarna',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);

        $this->assertNull(apply_filters('buckaroo_push_reservation', null, $order, $push));
        $this->assertSame('pending', wc_get_order($order->get_id())->get_status());
    }

    private function assertAuthorizedInProcessing(WC_Order $order): void
    {
        $this->assertSame('processing', $order->get_status());
        $this->assertSame([], OrderMeta::get($order, '_wc_order_captures', false), 'A reservation is not a capture');
        $this->assertSame(1, $this->countAuthorizedNotes($order));
        $this->assertSame('yes', $order->get_meta('buckaroo_is_reserved'));
        $this->assertSame('yes', $order->get_meta(KlarnaPushProcessor::RESERVATION_PUSH_META_KEY));
        $this->assertSame('yes', $order->get_meta('_wc_order_authorized'));
        $this->assertSame('25.00', $order->get_meta(KlarnaProcessor::RESERVED_AMOUNT_META_KEY));
        $this->assertSame('DATA-REQUEST-KEY', $order->get_meta(KlarnaProcessor::DATA_REQUEST_META_KEY));
        $this->assertSame('RESERVE-PUSH', $order->get_transaction_id());
    }

    private function countAuthorizedNotes(WC_Order $order): int
    {
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);

        return count(array_filter($notes, function ($note) {
            return strpos($note->content, 'Klarna payment authorized') === 0;
        }));
    }

    private function browserReturn(WC_Order $order, string $statusCode): void
    {
        if (function_exists('wc_load_cart') && WC()->cart === null) {
            wc_load_cart();
        }

        // The signature check is covered elsewhere; this drives the rest of the real return path.
        (new ReturnProcessor([
            'brq_statuscode' => $statusCode,
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'RESERVE-PUSH',
            'brq_transaction_method' => 'klarna',
            'brq_datarequest' => 'DATA-REQUEST-KEY',
            'brq_ordernumber' => (string) $order->get_id(),
        ], false))->handle(new KlarnaPayGateway());
    }

    private function reservationPush(WC_Order $order): void
    {
        $this->push($order, [
            'brq_action' => 'Reserve',
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'RESERVE-PUSH',
            'brq_transaction_method' => 'klarna',
            'brq_datarequest' => 'DATA-REQUEST-KEY',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);
    }

    private function push(WC_Order $order, array $data): void
    {
        if (function_exists('wc_load_cart') && WC()->cart === null) {
            wc_load_cart();
        }

        $responseParser = new FormDataParser($data);
        $responseParser->set('coreStatus', BuckarooTransactionStatus::fromTransactionStatus($responseParser->getStatusCode()));

        $reflectionMethod = new \ReflectionMethod(PushProcessor::class, 'onSuccess');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke(new PushProcessor(), $order->get_id(), $order, $responseParser);
    }

    private function createKlarnaOrder(): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Physical product');
        $product->set_regular_price('25.00');
        $product->set_price('25.00');
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, 1);
        $order->set_payment_method('buckaroo_klarnapay');
        $order->set_currency('EUR');
        $order->calculate_totals();
        $order->save();

        return wc_get_order($order->get_id());
    }
}
