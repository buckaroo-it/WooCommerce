<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaFulfillmentActions;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPushProcessor;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\ResponseParser\FormDataParser;
use PHPUnit\Framework\TestCase;

/**
 * The browser return and the reservation push of a Klarna (MoR) reserve can arrive
 * in any order; only the push may move the order to its reserved (on-hold) state,
 * and a reservation must never complete the order.
 */
class Test_KlarnaReservationReturnPush extends TestCase
{
    use HposStorage;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        $this->enableHpos();
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        new KlarnaFulfillmentActions();
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_return_before_reservation_push_leaves_the_order_reserved_and_unpaid(): void
    {
        $order = $this->createKlarnaOrder();

        $this->browserReturn($order, '190');
        $this->reservationPush($order);

        $this->assertReservedAndUnpaid(wc_get_order($order->get_id()));
    }

    public function test_reservation_push_before_return_leaves_the_order_reserved_and_unpaid(): void
    {
        $order = $this->createKlarnaOrder();

        $this->reservationPush($order);
        $this->browserReturn(wc_get_order($order->get_id()), '190');

        $this->assertReservedAndUnpaid(wc_get_order($order->get_id()));
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

    public function test_a_replayed_reservation_push_does_not_complete_the_order(): void
    {
        $order = $this->createKlarnaOrder();

        $this->reservationPush($order);
        $this->reservationPush(wc_get_order($order->get_id()));

        $this->assertReservedAndUnpaid(wc_get_order($order->get_id()));
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

    private function assertReservedAndUnpaid(WC_Order $order): void
    {
        $this->assertSame('on-hold', $order->get_status());
        $this->assertFalse($order->is_paid());
        $this->assertNull($order->get_date_paid());
        $this->assertSame('yes', $order->get_meta('buckaroo_is_reserved'));
        $this->assertSame('yes', $order->get_meta(KlarnaPushProcessor::RESERVATION_PUSH_META_KEY));
        $this->assertSame('yes', $order->get_meta('_wc_order_authorized'));
        $this->assertSame('25.00', $order->get_meta(KlarnaProcessor::RESERVED_AMOUNT_META_KEY));
        $this->assertSame('DATA-REQUEST-KEY', $order->get_meta(KlarnaProcessor::DATA_REQUEST_META_KEY));
        $this->assertSame('RESERVE-PUSH', $order->get_transaction_id());
    }

    private function browserReturn(WC_Order $order, string $statusCode): void
    {
        $responseParser = new FormDataParser([
            'brq_statuscode' => $statusCode,
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transaction_method' => 'klarna',
            'brq_datarequest' => 'DATA-REQUEST-KEY',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);
        $responseParser->set('coreStatus', BuckarooTransactionStatus::fromTransactionStatus($responseParser->getStatusCode()));

        (new KlarnaPayGateway())->newPaymentProcessorInstance($order)->beforeReturnHandler($responseParser, '');
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
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_klarnapay');
        $order->set_currency('EUR');
        $order->set_total(25.00);
        $order->save();

        return wc_get_order($order->get_id());
    }
}
