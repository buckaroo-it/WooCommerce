<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaCancelReservation;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;
use PHPUnit\Framework\TestCase;

/**
 * The reservation number and data request key tie an order to a Klarna reservation;
 * capture, refund and cancel all resolve it through them.
 */
class Test_KlarnaOrderMeta extends TestCase
{
    use HposStorage;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        $this->enableHpos();
    }

    protected function tearDown(): void
    {
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_klarnakp_reservation_number_round_trips_on_an_hpos_store()
    {
        $order = $this->createOrder();

        OrderMeta::update($order, '_buckaroo_klarnakp_reservation_number', '1234567890');

        $this->assertSame(
            '1234567890',
            OrderMeta::get($order->get_id(), '_buckaroo_klarnakp_reservation_number')
        );
        $this->assertSame(
            '',
            get_post_meta($order->get_id(), '_buckaroo_klarnakp_reservation_number', true),
            'The reservation number should no longer be written to post meta'
        );
    }

    public function test_klarna_data_request_key_round_trips_on_an_hpos_store()
    {
        $order = $this->createOrder();

        OrderMeta::update($order, KlarnaProcessor::DATA_REQUEST_META_KEY, 'DATAREQUESTKEY123');

        $this->assertSame(
            'DATAREQUESTKEY123',
            OrderMeta::get($order->get_id(), KlarnaProcessor::DATA_REQUEST_META_KEY)
        );
    }

    public function test_a_missing_reservation_number_reads_back_as_an_empty_string()
    {
        $order = $this->createOrder();

        $reservationNumber = OrderMeta::get($order->get_id(), '_buckaroo_klarnakp_reservation_number');

        $this->assertSame('', $reservationNumber);
        $this->assertFalse(is_string($reservationNumber) && strlen($reservationNumber) > 0);
    }

    public function test_cancel_reservation_action_is_offered_for_a_reserved_klarnakp_order()
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_klarnakp');
        $order->save();

        OrderMeta::update($order, 'buckaroo_is_reserved', 'yes');

        $actions = (new KlarnaCancelReservation())->add_cancel_option([], wc_get_order($order->get_id()));

        $this->assertArrayHasKey('buckaroo_klarnakp_cancel_reservation', $actions);
    }

    public function test_cancel_reservation_action_is_not_offered_when_nothing_is_reserved()
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_klarnakp');
        $order->save();

        $actions = (new KlarnaCancelReservation())->add_cancel_option([], wc_get_order($order->get_id()));

        $this->assertArrayNotHasKey('buckaroo_klarnakp_cancel_reservation', $actions);
    }

    public function test_cancel_reservation_action_is_not_offered_for_another_payment_method()
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_ideal');
        $order->save();

        OrderMeta::update($order, 'buckaroo_is_reserved', 'yes');

        $actions = (new KlarnaCancelReservation())->add_cancel_option([], wc_get_order($order->get_id()));

        $this->assertArrayNotHasKey('buckaroo_klarnakp_cancel_reservation', $actions);
    }
}
