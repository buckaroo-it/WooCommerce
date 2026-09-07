<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Order\OrderMeta;
use PHPUnit\Framework\TestCase;

/**
 * Runs with HPOS on and posts sync off, where post meta and order meta diverge.
 */
class Test_OrderMeta extends TestCase
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

    public function test_writes_land_in_order_meta_not_post_meta()
    {
        $order = $this->createOrder();

        OrderMeta::update($order->get_id(), '_pushallowed', 'ok');

        $this->assertSame('ok', wc_get_order($order->get_id())->get_meta('_pushallowed', true));
        $this->assertSame('', get_post_meta($order->get_id(), '_pushallowed', true));
    }

    public function test_writes_land_in_the_wc_orders_meta_table()
    {
        global $wpdb;

        $order = $this->createOrder();

        OrderMeta::update($order, '_buckaroo_klarnakp_reservation_number', '556677');

        $this->assertSame(
            '556677',
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta
                     WHERE order_id = %d AND meta_key = %s",
                    $order->get_id(),
                    '_buckaroo_klarnakp_reservation_number'
                )
            )
        );
        $this->assertNull(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->postmeta}
                     WHERE post_id = %d AND meta_key = %s",
                    $order->get_id(),
                    '_buckaroo_klarnakp_reservation_number'
                )
            ),
            'Nothing should be left behind on the placeholder post row'
        );
    }

    public function test_get_single_returns_empty_string_for_missing_key()
    {
        $order = $this->createOrder();

        $this->assertSame('', OrderMeta::get($order->get_id(), '_buckaroo_missing_key'));
        $this->assertSame(
            get_post_meta($order->get_id(), '_buckaroo_missing_key', true),
            OrderMeta::get($order->get_id(), '_buckaroo_missing_key')
        );
    }

    public function test_get_non_single_returns_empty_array_for_missing_key()
    {
        $order = $this->createOrder();

        $this->assertSame([], OrderMeta::get($order->get_id(), '_buckaroo_missing_key', false));
    }

    public function test_get_non_single_returns_plain_values_not_meta_objects()
    {
        $order = $this->createOrder();

        OrderMeta::add($order, '_wc_order_captures', ['id' => 'a', 'amount' => '1.00']);
        OrderMeta::add($order, '_wc_order_captures', ['id' => 'b', 'amount' => '2.00']);

        $captures = OrderMeta::get($order->get_id(), '_wc_order_captures', false);

        $this->assertSame(
            [
                ['id' => 'a', 'amount' => '1.00'],
                ['id' => 'b', 'amount' => '2.00'],
            ],
            $captures
        );
    }

    /** AbstractRefundProcessor takes end($captures) to choose what it refunds against. */
    public function test_non_single_returns_values_in_meta_id_order_not_storage_order()
    {
        $order = $this->createOrder();

        OrderMeta::add($order, '_wc_order_captures', ['id' => 'first', 'transaction_id' => 'TX_FIRST']);
        OrderMeta::add($order, '_wc_order_captures', ['id' => 'second', 'transaction_id' => 'TX_SECOND']);

        $fresh = wc_get_order($order->get_id());

        // The HPOS meta query has no ORDER BY, so the rows can arrive in any order.
        // MySQL happens to return primary-key order here, which would make this test
        // vacuous, so feed the seam genuinely reversed rows.
        $this->reverseLoadedMetaData($fresh);

        $captures = OrderMeta::get($fresh, '_wc_order_captures', false);

        $this->assertCount(2, $captures, 'Both rows should be read back');
        $this->assertSame(
            ['first', 'second'],
            array_column($captures, 'id'),
            'Values have to come back in meta_id order, whatever order storage returned'
        );

        $lastCapture = end($captures);
        $this->assertSame(
            'TX_SECOND',
            $lastCapture['transaction_id'],
            'end() has to give the newest capture, which is what a refund is sent against'
        );
    }

    /**
     * Reverse the order object's already-loaded meta rows, simulating storage handing
     * them back in a different order than meta_id ascending.
     */
    private function reverseLoadedMetaData(WC_Order $order): void
    {
        $property = new \ReflectionProperty(WC_Data::class, 'meta_data');
        $property->setAccessible(true);
        $property->setValue($order, array_reverse($property->getValue($order)));
    }

    public function test_update_replaces_the_existing_value()
    {
        $order = $this->createOrder();

        OrderMeta::update($order, '_wc_order_amount_captured', '10.00');
        OrderMeta::update($order, '_wc_order_amount_captured', '25.00');

        $this->assertSame('25.00', OrderMeta::get($order->get_id(), '_wc_order_amount_captured'));
        $this->assertSame(['25.00'], OrderMeta::get($order->get_id(), '_wc_order_amount_captured', false));
    }

    public function test_add_unique_writes_nothing_and_returns_false_when_key_exists()
    {
        $order = $this->createOrder();

        $this->assertTrue(OrderMeta::add($order, '_pushallowed', 'ok', true));
        $this->assertFalse(OrderMeta::add($order, '_pushallowed', 'second', true));

        $this->assertSame(['ok'], OrderMeta::get($order->get_id(), '_pushallowed', false));
    }

    public function test_add_without_unique_appends_a_second_value()
    {
        $order = $this->createOrder();

        OrderMeta::add($order, 'buckaroo_capture', 'first');
        OrderMeta::add($order, 'buckaroo_capture', 'second');

        $this->assertSame(['first', 'second'], OrderMeta::get($order->get_id(), 'buckaroo_capture', false));
    }

    public function test_delete_removes_every_value_for_the_key()
    {
        $order = $this->createOrder();

        OrderMeta::add($order, 'buckaroo_capture', 'first');
        OrderMeta::add($order, 'buckaroo_capture', 'second');

        $this->assertTrue(OrderMeta::delete($order, 'buckaroo_capture'));
        $this->assertSame([], OrderMeta::get($order->get_id(), 'buckaroo_capture', false));
    }

    public function test_several_writes_on_one_order_object_persist_without_an_explicit_save()
    {
        $order = $this->createOrder();

        OrderMeta::update($order, '_wc_order_is_captured', true);
        OrderMeta::update($order, '_wc_order_amount_captured', '12.34');
        OrderMeta::add($order, '_capturebuckarooABC123', 'ok', true);

        $fresh = wc_get_order($order->get_id());

        $this->assertSame('1', $fresh->get_meta('_wc_order_is_captured', true));
        $this->assertSame('12.34', $fresh->get_meta('_wc_order_amount_captured', true));
        $this->assertSame('ok', $fresh->get_meta('_capturebuckarooABC123', true));
    }

    public function test_writes_through_an_order_object_are_visible_by_id()
    {
        $order = $this->createOrder();

        OrderMeta::update($order, '_buckaroo_klarnakp_reservation_number', '998877');

        $this->assertSame(
            '998877',
            OrderMeta::get($order->get_id(), '_buckaroo_klarnakp_reservation_number')
        );
    }

    public function test_non_order_id_falls_back_to_post_meta()
    {
        $postId = wp_insert_post([
            'post_title' => 'Not an order',
            'post_type' => 'post',
            'post_status' => 'publish',
        ]);

        $this->assertTrue(OrderMeta::update($postId, '_buckaroo_probe', 'x'));
        $this->assertSame('x', OrderMeta::get($postId, '_buckaroo_probe'));
        $this->assertSame('x', get_post_meta($postId, '_buckaroo_probe', true));

        $this->assertSame(['x'], OrderMeta::get($postId, '_buckaroo_probe', false));

        $this->assertFalse(OrderMeta::add($postId, '_buckaroo_probe', 'y', true));
        $this->assertSame(['x'], OrderMeta::get($postId, '_buckaroo_probe', false));

        $this->assertTrue(OrderMeta::delete($postId, '_buckaroo_probe'));
        $this->assertSame('', OrderMeta::get($postId, '_buckaroo_probe'));

        wp_delete_post($postId, true);
    }

    public function test_unknown_id_does_not_fatal()
    {
        $unknownId = 999999999;

        $this->assertSame('', OrderMeta::get($unknownId, '_buckaroo_probe'));
        $this->assertSame([], OrderMeta::get($unknownId, '_buckaroo_probe', false));
    }
}
