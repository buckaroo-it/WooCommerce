<?php

namespace Buckaroo\Woocommerce\Install\Migration\Versions;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Buckaroo\Woocommerce\Install\Migration\Migration;
use Buckaroo\Woocommerce\Services\Logger;
use Throwable;
use WC_Abstract_Order;

/**
 * Copies Buckaroo order meta written by older versions out of post meta onto the
 * order, where the order API can see it under HPOS.
 *
 * Copies rather than moves, so a downgrade still finds its data in post meta, and
 * never overwrites, which also makes a rerun a no-op.
 */
class MigrateOrderMetaToHpos implements Migration
{
    public $version = '4.9.1';

    /** Batched through Action Scheduler; running inline on upgrade would time out. */
    public const BATCH_HOOK = 'buckaroo_migrate_order_meta_to_hpos_batch';

    public const ACTION_GROUP = 'buckaroo';

    public const BATCH_SIZE = 50;

    public const META_KEYS = [
        '_wc_order_selected_payment_method',
        '_wc_order_payment_issuer',
        '_wc_order_authorized',
        '_wc_order_is_captured',
        '_wc_order_amount_captured',
        '_wc_order_captures',
        '_payment_method_transaction',
        '_pushallowed',
        '_payload_birthday',
        '_payload_encrypted_card_data',
        '_buckaroo_order_in_test_mode',
        '_buckaroo_klarnakp_reservation_number',
        '_buckaroo_klarna_data_request_key',
        'buckaroo_settlement',
        'buckaroo_capture',
        'buckaroo_captures_refunded',
        'buckaroo_is_reserved',
    ];

    /** Carry a transaction key, so they can only be matched by prefix. */
    public const META_KEY_PREFIXES = [
        '_capturebuckaroo',
        '_refundbuckaroo',
    ];

    /** Hold more than one row per order, so they are compared row by row. */
    public const MULTI_ROW_META_KEYS = [
        '_wc_order_captures',
        'buckaroo_capture',
    ];

    /** Kept outside the queued action so a broken chain can be resumed. */
    public const CURSOR_OPTION = 'buckaroo_order_meta_hpos_cursor';

    /** Autoloaded: read on every request that reaches the resume check. */
    public const COMPLETE_OPTION = 'buckaroo_order_meta_hpos_complete';

    /** Called on every request: a queued batch can run long after the upgrade. */
    public static function registerBatchHandler(): void
    {
        add_action(self::BATCH_HOOK, [self::class, 'runBatch'], 10, 1);
        add_action('admin_init', [self::class, 'resumeIfStalled']);
    }

    public function execute()
    {
        if (! self::isNeeded()) {
            return;
        }

        update_option(self::COMPLETE_OPTION, 'no', true);
        update_option(self::CURSOR_OPTION, 0, false);

        self::scheduleBatch(0);
    }

    /** Requeue when the chain was lost: no Action Scheduler at upgrade, or a dead batch. */
    public static function resumeIfStalled(): void
    {
        if (self::isComplete() || ! self::isNeeded()) {
            return;
        }

        if (! function_exists('as_has_scheduled_action') || get_transient(self::CURSOR_OPTION . '_check')) {
            return;
        }

        set_transient(self::CURSOR_OPTION . '_check', 1, 5 * MINUTE_IN_SECONDS);

        $cursor = (int) get_option(self::CURSOR_OPTION, 0);

        if (! as_has_scheduled_action(self::BATCH_HOOK, [$cursor], self::ACTION_GROUP)) {
            self::scheduleBatch($cursor);
        }
    }

    /**
     * @param  int  $afterOrderId  Cursor: only orders with a higher id are considered
     */
    public static function runBatch($afterOrderId = 0): void
    {
        if (! self::isNeeded()) {
            return;
        }

        $orderIds = self::findOrderIdsWithBuckarooPostMeta((int) $afterOrderId);

        if (empty($orderIds)) {
            update_option(self::COMPLETE_OPTION, 'yes', true);

            return;
        }

        foreach ($orderIds as $orderId) {
            try {
                self::copyOrder($orderId);
            } catch (Throwable $th) {
                // One unreadable order must not strand the rest of the batch.
                Logger::log(__METHOD__ . '|order:' . $orderId, $th);
            }
        }

        $cursor = (int) end($orderIds);

        update_option(self::CURSOR_OPTION, $cursor, false);

        self::scheduleBatch($cursor);
    }

    /**
     * Copy one order now, if the batch has not reached it yet. Without this a replayed
     * refund push on an uncopied order reads an empty lock and refunds twice.
     *
     * @param  int|WC_Abstract_Order  $order
     */
    public static function ensureOrderMigrated($order): void
    {
        if (self::isComplete() || ! self::isNeeded()) {
            return;
        }

        $orderId = $order instanceof WC_Abstract_Order ? $order->get_id() : (int) $order;

        if ($orderId <= 0) {
            return;
        }

        try {
            self::copyOrder($orderId);
        } catch (Throwable $th) {
            Logger::log(__METHOD__ . '|order:' . $orderId, $th);
        }
    }

    public static function isComplete(): bool
    {
        return get_option(self::COMPLETE_OPTION) === 'yes';
    }

    /**
     * Legacy stores need nothing: the data is already where the code looks.
     *
     * Sync-on stores still need the copy. WooCommerce syncs on a date_updated_gmt /
     * post_modified_gmt mismatch, and update_post_meta never touches post_modified.
     */
    public static function isNeeded(): bool
    {
        return class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * @return int[] Ascending, so the cursor can advance past the batch
     */
    private static function findOrderIdsWithBuckarooPostMeta(int $afterOrderId): array
    {
        global $wpdb;

        $placeholders = implode(', ', array_fill(0, count(self::META_KEYS), '%s'));
        $conditions = ["meta_key IN ({$placeholders})"];
        $values = self::META_KEYS;

        foreach (self::META_KEY_PREFIXES as $prefix) {
            $conditions[] = 'meta_key LIKE %s';
            // esc_like: every prefix starts with _, which is a LIKE wildcard.
            $values[] = $wpdb->esc_like($prefix) . '%';
        }

        $values[] = $afterOrderId;
        $values[] = self::BATCH_SIZE;

        return array_map(
            'intval',
            $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                     WHERE (" . implode(' OR ', $conditions) . ')
                       AND post_id > %d
                     ORDER BY post_id ASC
                     LIMIT %d',
                    $values
                )
            )
        );
    }

    private static function copyOrder(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Abstract_Order) {
            return;
        }

        $changed = false;

        foreach (self::readBuckarooPostMeta($orderId) as $key => $values) {
            if (! in_array($key, self::MULTI_ROW_META_KEYS, true)) {
                // Never overwrite, which also makes a rerun a no-op.
                if ($order->meta_exists($key)) {
                    continue;
                }

                foreach ($values as $value) {
                    $order->add_meta_data($key, $value, false);
                }

                $changed = true;

                continue;
            }

            // Skipping the whole key would drop the legacy captures of an order
            // captured again before the migration reached it, and OrderCapture bases
            // the still-capturable amount on those rows.
            $existing = array_map(
                function ($meta) {
                    return maybe_serialize($meta->value);
                },
                $order->get_meta($key, false)
            );

            foreach ($values as $value) {
                $fingerprint = maybe_serialize($value);

                if (in_array($fingerprint, $existing, true)) {
                    continue;
                }

                $order->add_meta_data($key, $value, false);
                $existing[] = $fingerprint;
                $changed = true;
            }
        }

        if (! $changed) {
            return;
        }

        // A meta change on HPOS normally triggers a full $order->save(), restamping
        // date_modified and firing woocommerce_update_order. Backfilling must not do
        // that for every order in the shop.
        $skipFullSave = function () {
            return false;
        };

        add_filter('woocommerce_orders_table_datastore_should_save_after_meta_change', $skipFullSave, PHP_INT_MAX);

        try {
            $order->save_meta_data();
        } finally {
            remove_filter('woocommerce_orders_table_datastore_should_save_after_meta_change', $skipFullSave, PHP_INT_MAX);
        }
    }

    /**
     * @return array<string, array<int, mixed>> Every value per key, in insertion order
     */
    private static function readBuckarooPostMeta(int $orderId): array
    {
        global $wpdb;

        $placeholders = implode(', ', array_fill(0, count(self::META_KEYS), '%s'));
        $conditions = ["meta_key IN ({$placeholders})"];
        $values = self::META_KEYS;

        foreach (self::META_KEY_PREFIXES as $prefix) {
            $conditions[] = 'meta_key LIKE %s';
            $values[] = $wpdb->esc_like($prefix) . '%';
        }

        array_unshift($values, $orderId);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta}
                 WHERE post_id = %d
                   AND (" . implode(' OR ', $conditions) . ')
                 ORDER BY meta_id ASC',
                $values
            )
        );

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->meta_key][] = maybe_unserialize($row->meta_value);
        }

        return $grouped;
    }

    private static function scheduleBatch(int $afterOrderId): void
    {
        if (! function_exists('as_enqueue_async_action')) {
            // Skip rather than run an unbounded copy inline; resumeIfStalled retries.
            Logger::log(__METHOD__, 'Action Scheduler is unavailable, order meta was not migrated');

            return;
        }

        if (
            function_exists('as_has_scheduled_action') &&
            as_has_scheduled_action(self::BATCH_HOOK, [$afterOrderId], self::ACTION_GROUP)
        ) {
            return;
        }

        as_enqueue_async_action(self::BATCH_HOOK, [$afterOrderId], self::ACTION_GROUP);
    }
}
