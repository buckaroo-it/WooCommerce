<?php

declare(strict_types=1);

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Switches the test store between HPOS and the legacy post storage.
 */
trait HposStorage
{
    /** @var int[] */
    private $createdOrderIds = [];

    /** @var bool */
    private static $purgedOrphanedRows = false;

    /** HPOS on with posts sync off: where post meta and order meta diverge. */
    protected function enableHpos(): void
    {
        $this->allowStorageSwitch();
        update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');
        update_option('woocommerce_custom_orders_table_enabled', 'yes');

        if (! OrderUtil::custom_orders_table_usage_is_enabled()) {
            $this->markTestSkipped('Could not enable HPOS in the test environment');
        }

        $this->purgeOrphanedOrderRows();
    }

    /**
     * The bootstrap resets wp_posts between runs but not the order tables, so order
     * ids restart and can collide with a previous run's rows.
     */
    private function purgeOrphanedOrderRows(): void
    {
        if (self::$purgedOrphanedRows) {
            return;
        }

        self::$purgedOrphanedRows = true;

        global $wpdb;

        // Raw deletes: only ever run against the test suite's own database.
        if (! defined('WP_TESTS_DOMAIN') || strpos($wpdb->prefix, 'wptests_') !== 0) {
            return;
        }

        foreach (['wc_orders' => 'id', 'wc_orders_meta' => 'order_id', 'wc_order_addresses' => 'order_id', 'wc_order_operational_data' => 'order_id'] as $table => $column) {
            $wpdb->query(
                "DELETE FROM {$wpdb->prefix}{$table}
                 WHERE {$column} NOT IN (SELECT ID FROM {$wpdb->posts})"
            );
        }
    }

    /** Restore legacy storage, so later tests are unaffected. */
    protected function disableHpos(): void
    {
        $this->allowStorageSwitch();
        update_option('woocommerce_custom_orders_table_enabled', 'no');
    }

    private function allowStorageSwitch(): void
    {
        add_filter('wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true');
    }

    /** A saved order with no meta of its own, in whichever storage is authoritative. */
    protected function createOrder(): WC_Order
    {
        $order = new WC_Order();
        $order->set_status('pending');
        $order->save();

        $this->createdOrderIds[] = $order->get_id();

        $stale = wc_get_order($order->get_id());

        if (! $stale instanceof WC_Order) {
            $this->fail(sprintf(
                'Order %d could not be read back after being saved. The order tables and wp_posts are probably out of step - check for leftover rows from an earlier run.',
                $order->get_id()
            ));
        }

        foreach ($stale->get_meta_data() as $meta) {
            $stale->delete_meta_data($meta->key);
        }
        $stale->save_meta_data();

        return wc_get_order($order->get_id());
    }

    /** Remove this test's orders, so they are not carried into the next run. */
    protected function deleteCreatedOrders(): void
    {
        foreach ($this->createdOrderIds as $orderId) {
            $order = wc_get_order($orderId);

            if ($order instanceof WC_Order) {
                $order->delete(true);
            }
        }

        $this->createdOrderIds = [];
    }
}
