<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Install\Migration\Migration;
use Buckaroo\Woocommerce\Install\Migration\MigrationHandler;
use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateOrderMetaToHpos;
use Buckaroo\Woocommerce\Order\OrderMeta;
use PHPUnit\Framework\TestCase;

/**
 * Older orders keep their Buckaroo data in post meta once HPOS is authoritative.
 */
class Test_MigrateOrderMetaToHpos extends TestCase
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
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(MigrateOrderMetaToHpos::BATCH_HOOK);
        }

        delete_option(MigrateOrderMetaToHpos::CURSOR_OPTION);
        delete_option(MigrateOrderMetaToHpos::COMPLETE_OPTION);
        delete_transient(MigrateOrderMetaToHpos::CURSOR_OPTION . '_check');

        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    /**
     * A push after the upgrade writes _pushallowed to order meta while
     * _refundbuckaroo<key> is still in post meta, so a replay would refund again.
     */
    public function test_a_replayed_refund_cannot_slip_through_before_the_batch_copy_arrives()
    {
        $order = $this->createOrder();
        $transactionKey = 'LEGACY_TX';

        update_post_meta($order->get_id(), '_pushallowed', 'ok');
        update_post_meta($order->get_id(), '_refundbuckaroo' . $transactionKey, 'ok');

        // A push lands before the batch copy reaches this order.
        OrderMeta::add($order, '_pushallowed', 'ok', true);

        $this->assertSame(
            '',
            OrderMeta::get($order->get_id(), '_refundbuckaroo' . $transactionKey),
            'This is the dangerous state the fix has to remove'
        );

        MigrateOrderMetaToHpos::ensureOrderMigrated($order->get_id());

        $this->assertSame(
            'ok',
            OrderMeta::get($order->get_id(), '_refundbuckaroo' . $transactionKey),
            'The refund lock has to be readable, or the replay refunds a second time'
        );
    }

    public function test_ensure_order_migrated_costs_nothing_once_the_copy_is_finished()
    {
        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_pushallowed', 'ok');

        update_option(MigrateOrderMetaToHpos::COMPLETE_OPTION, 'yes', false);

        MigrateOrderMetaToHpos::ensureOrderMigrated($order->get_id());

        $this->assertSame('', OrderMeta::get($order->get_id(), '_pushallowed'));
    }

    public function test_an_empty_batch_marks_the_copy_complete()
    {
        $order = $this->createOrder();

        MigrateOrderMetaToHpos::runBatch($order->get_id());

        $this->assertTrue(MigrateOrderMetaToHpos::isComplete());
    }

    /**
     * upgrader_process_complete only fires for updates through the WordPress updater,
     * so a git or rsync deploy never sets the transient and execute() never runs.
     */
    public function test_the_copy_still_happens_when_the_upgrade_hook_never_fired()
    {
        if (! function_exists('as_has_scheduled_action')) {
            $this->markTestSkipped('Action Scheduler is not available');
        }

        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_pushallowed', 'ok');

        // Exactly the state after a file-copy deploy: execute() was never called, so
        // neither option exists.
        delete_option(MigrateOrderMetaToHpos::COMPLETE_OPTION);
        delete_option(MigrateOrderMetaToHpos::CURSOR_OPTION);
        delete_transient(MigrateOrderMetaToHpos::CURSOR_OPTION . '_check');
        as_unschedule_all_actions(MigrateOrderMetaToHpos::BATCH_HOOK);

        MigrateOrderMetaToHpos::resumeIfStalled();

        $this->assertTrue(
            as_has_scheduled_action(MigrateOrderMetaToHpos::BATCH_HOOK, [0], MigrateOrderMetaToHpos::ACTION_GROUP),
            'admin_init has to queue the copy even though the upgrade hook never fired'
        );

        MigrateOrderMetaToHpos::runBatch(0);

        $this->assertSame('ok', OrderMeta::get($order->get_id(), '_pushallowed'));
    }

    public function test_a_stalled_chain_is_requeued_from_the_stored_cursor()
    {
        if (! function_exists('as_has_scheduled_action')) {
            $this->markTestSkipped('Action Scheduler is not available');
        }

        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_pushallowed', 'ok');

        // A batch ran and stored its cursor, then the queue was lost.
        update_option(MigrateOrderMetaToHpos::COMPLETE_OPTION, 'no', false);
        update_option(MigrateOrderMetaToHpos::CURSOR_OPTION, $order->get_id() - 1, false);
        as_unschedule_all_actions(MigrateOrderMetaToHpos::BATCH_HOOK);

        MigrateOrderMetaToHpos::resumeIfStalled();

        $this->assertTrue(
            as_has_scheduled_action(
                MigrateOrderMetaToHpos::BATCH_HOOK,
                [$order->get_id() - 1],
                MigrateOrderMetaToHpos::ACTION_GROUP
            ),
            'A lost chain has to be picked back up from the stored cursor'
        );
    }

    public function test_migration_is_registered_at_version_4_9_1()
    {
        $migration = new MigrateOrderMetaToHpos();

        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertSame('4.9.1', $migration->version);
    }

    /**
     * MigrationHandler keeps only version <= Plugin::VERSION and > the database
     * version. Get either wrong and the copy silently never runs.
     */
    public function test_migration_handler_actually_selects_this_migration()
    {
        $version = (new MigrateOrderMetaToHpos())->version;

        $this->assertTrue(
            version_compare($version, Plugin::VERSION, '<='),
            sprintf(
                'Migration version %s is above Plugin::VERSION %s, so MigrationHandler would filter it out and it would never run.',
                $version,
                Plugin::VERSION
            )
        );

        $this->assertTrue(
            version_compare($version, '4.9.0', '>'),
            'Migration version has to be above the previous release, or stores already on it would skip the copy.'
        );

        $handler = new MigrationHandler();
        $method = new \ReflectionMethod(MigrationHandler::class, 'get_migration_items');
        $method->setAccessible(true);

        $registered = array_map('get_class', $method->invoke($handler));

        $this->assertContains(MigrateOrderMetaToHpos::class, $registered);
    }

    public function test_it_copies_a_static_key_from_post_meta_to_order_meta()
    {
        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_wc_order_selected_payment_method', 'KlarnaPay');

        $this->assertSame(
            '',
            OrderMeta::get($order->get_id(), '_wc_order_selected_payment_method'),
            'The value should be invisible to the order API before the migration'
        );

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $this->assertSame(
            'KlarnaPay',
            OrderMeta::get($order->get_id(), '_wc_order_selected_payment_method')
        );
    }

    public function test_it_leaves_the_post_meta_rows_in_place()
    {
        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_pushallowed', 'ok');

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $this->assertSame(
            'ok',
            get_post_meta($order->get_id(), '_pushallowed', true),
            'A downgrade to an older plugin has to still find its data in post meta'
        );
    }

    public function test_it_copies_every_row_of_a_multi_row_key()
    {
        $order = $this->createOrder();
        add_post_meta($order->get_id(), '_wc_order_captures', ['id' => 'a', 'amount' => '1.00']);
        add_post_meta($order->get_id(), '_wc_order_captures', ['id' => 'b', 'amount' => '2.00']);
        add_post_meta($order->get_id(), 'buckaroo_capture', 'first');
        add_post_meta($order->get_id(), 'buckaroo_capture', 'second');

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $this->assertSame(
            [
                ['id' => 'a', 'amount' => '1.00'],
                ['id' => 'b', 'amount' => '2.00'],
            ],
            OrderMeta::get($order->get_id(), '_wc_order_captures', false)
        );
        $this->assertSame(
            ['first', 'second'],
            OrderMeta::get($order->get_id(), 'buckaroo_capture', false)
        );
    }

    public function test_it_copies_an_array_value_back_as_an_array()
    {
        $order = $this->createOrder();
        update_post_meta($order->get_id(), 'buckaroo_settlement', ['TX_ONE' => 20.00]);

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $this->assertSame(
            ['TX_ONE' => 20.00],
            OrderMeta::get($order->get_id(), 'buckaroo_settlement')
        );
    }

    /** Prefix starts with an underscore, which is a LIKE wildcard. */
    public function test_it_copies_the_dynamic_capture_and_refund_locks_by_prefix()
    {
        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_capturebuckarooTXKEY111', 'ok');
        update_post_meta($order->get_id(), '_refundbuckarooTXKEY222', 'ok');

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $this->assertSame('ok', OrderMeta::get($order->get_id(), '_capturebuckarooTXKEY111'));
        $this->assertSame('ok', OrderMeta::get($order->get_id(), '_refundbuckarooTXKEY222'));
    }

    public function test_running_it_twice_produces_no_duplicates()
    {
        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_pushallowed', 'ok');
        add_post_meta($order->get_id(), 'buckaroo_capture', 'first');
        add_post_meta($order->get_id(), 'buckaroo_capture', 'second');

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);
        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $this->assertSame(['ok'], OrderMeta::get($order->get_id(), '_pushallowed', false));
        $this->assertSame(['first', 'second'], OrderMeta::get($order->get_id(), 'buckaroo_capture', false));
    }

    /**
     * A capture after the upgrade puts one row on the order while the legacy rows are
     * still in post meta. Losing those would allow an over-capture.
     */
    public function test_it_copies_legacy_capture_rows_even_when_the_order_already_has_one()
    {
        $order = $this->createOrder();

        add_post_meta($order->get_id(), '_wc_order_captures', ['id' => 'legacy-1', 'amount' => '10.00']);
        add_post_meta($order->get_id(), '_wc_order_captures', ['id' => 'legacy-2', 'amount' => '20.00']);

        // A capture taken after the upgrade, written by the new code onto the order.
        OrderMeta::add($order, '_wc_order_captures', ['id' => 'post-upgrade', 'amount' => '5.00']);

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $captures = OrderMeta::get($order->get_id(), '_wc_order_captures', false);

        $this->assertSame(
            ['post-upgrade', 'legacy-1', 'legacy-2'],
            array_column($captures, 'id'),
            'The legacy capture rows have to survive alongside the newer one'
        );
    }

    public function test_multi_row_keys_are_not_duplicated_when_the_migration_reruns()
    {
        $order = $this->createOrder();

        add_post_meta($order->get_id(), '_wc_order_captures', ['id' => 'legacy-1', 'amount' => '10.00']);
        add_post_meta($order->get_id(), 'buckaroo_capture', 'payload-one');
        add_post_meta($order->get_id(), 'buckaroo_capture', 'payload-two');

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);
        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);
        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $this->assertSame(
            ['legacy-1'],
            array_column(OrderMeta::get($order->get_id(), '_wc_order_captures', false), 'id')
        );
        $this->assertSame(
            ['payload-one', 'payload-two'],
            OrderMeta::get($order->get_id(), 'buckaroo_capture', false)
        );
    }

    public function test_it_never_overwrites_a_value_already_on_the_order()
    {
        $order = $this->createOrder();
        OrderMeta::update($order, '_wc_order_amount_captured', '99.99');
        update_post_meta($order->get_id(), '_wc_order_amount_captured', '10.00');

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $this->assertSame('99.99', OrderMeta::get($order->get_id(), '_wc_order_amount_captured'));
        $this->assertSame(['99.99'], OrderMeta::get($order->get_id(), '_wc_order_amount_captured', false));
    }

    /**
     * The failure this migration exists to prevent: without the locks the new code
     * reads an empty value on a legacy order, decides the refund has not happened and
     * refunds a second time.
     */
    public function test_the_idempotency_locks_are_readable_by_the_new_code_after_migrating()
    {
        $order = $this->createOrder();
        $transactionKey = 'LEGACY_TX_KEY';
        update_post_meta($order->get_id(), '_pushallowed', 'ok');
        update_post_meta($order->get_id(), '_refundbuckaroo' . $transactionKey, 'ok');

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        // These two reads are exactly what RefundAction::initiateExternalServiceRefund
        // does before deciding whether to create a refund.
        $this->assertSame('ok', OrderMeta::get($order->get_id(), '_pushallowed'));
        $this->assertNotEmpty(OrderMeta::get($order->get_id(), '_refundbuckaroo' . $transactionKey));
    }

    public function test_it_does_nothing_when_hpos_is_off()
    {
        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_pushallowed', 'ok');

        $this->disableHpos();

        $this->assertFalse(MigrateOrderMetaToHpos::isNeeded());

        MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

        $this->enableHpos();
        $this->assertSame('', OrderMeta::get($order->get_id(), '_pushallowed'));
    }

    /**
     * WooCommerce's own sync cannot rescue these rows: it selects orders by a mismatch
     * between date_updated_gmt and post_modified_gmt, and update_post_meta() never
     * touches post_modified. So a store with sync on needs the copy too.
     */
    public function test_it_still_copies_when_posts_sync_is_on()
    {
        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_pushallowed', 'ok');

        update_option('woocommerce_custom_orders_table_data_sync_enabled', 'yes');

        try {
            $this->assertTrue(MigrateOrderMetaToHpos::isNeeded());

            MigrateOrderMetaToHpos::runBatch($order->get_id() - 1);

            $this->assertSame('ok', OrderMeta::get($order->get_id(), '_pushallowed'));
        } finally {
            update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');
        }
    }

    /**
     * The cursor only advances when the batch finishes, so a single unreadable order
     * must not strand every order after it.
     */
    public function test_one_failing_order_does_not_stop_the_rest_of_the_batch()
    {
        $failing = $this->createOrder();
        $healthy = $this->createOrder();
        update_post_meta($failing->get_id(), '_pushallowed', 'ok');
        update_post_meta($healthy->get_id(), '_pushallowed', 'ok');

        // HPOS writes order meta with a raw $wpdb->insert, so no WP meta hook fires.
        // Blow up on the first order meta insert instead, which belongs to the first
        // order of the batch. Orders are copied in ascending id order.
        $blownUp = false;
        $blowUp = function ($query) use (&$blownUp) {
            if (! $blownUp && stripos($query, 'INSERT') === 0 && stripos($query, 'wc_orders_meta') !== false) {
                $blownUp = true;

                throw new RuntimeException('Simulated failure while writing the order meta');
            }

            return $query;
        };
        add_filter('query', $blowUp);

        try {
            MigrateOrderMetaToHpos::runBatch($failing->get_id() - 1);
        } finally {
            remove_filter('query', $blowUp);
        }

        $this->assertTrue($blownUp, 'The simulated failure never fired');

        $this->assertSame(
            '',
            OrderMeta::get($failing->get_id(), '_pushallowed'),
            'The simulated failure has to actually stop this order being copied'
        );
        $this->assertSame(
            'ok',
            OrderMeta::get($healthy->get_id(), '_pushallowed'),
            'The order after the failing one still has to be copied'
        );
    }

    public function test_execute_queues_a_batch_instead_of_copying_synchronously()
    {
        if (! function_exists('as_has_scheduled_action')) {
            $this->markTestSkipped('Action Scheduler is not available');
        }

        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_pushallowed', 'ok');

        (new MigrateOrderMetaToHpos())->execute();

        $this->assertTrue(
            as_has_scheduled_action(MigrateOrderMetaToHpos::BATCH_HOOK, [0], MigrateOrderMetaToHpos::ACTION_GROUP),
            'The upgrade should queue the copy, not run it inline'
        );
        $this->assertSame(
            '',
            OrderMeta::get($order->get_id(), '_pushallowed'),
            'Nothing should have been copied yet'
        );
    }

    public function test_a_batch_covers_every_order_in_it_and_advances_the_cursor()
    {
        if (! function_exists('as_has_scheduled_action')) {
            $this->markTestSkipped('Action Scheduler is not available');
        }

        $first = $this->createOrder();
        $second = $this->createOrder();
        update_post_meta($first->get_id(), '_pushallowed', 'ok');
        update_post_meta($second->get_id(), '_pushallowed', 'ok');

        MigrateOrderMetaToHpos::runBatch($first->get_id() - 1);

        $this->assertSame('ok', OrderMeta::get($first->get_id(), '_pushallowed'));
        $this->assertSame('ok', OrderMeta::get($second->get_id(), '_pushallowed'));

        $this->assertTrue(
            as_has_scheduled_action(
                MigrateOrderMetaToHpos::BATCH_HOOK,
                [$second->get_id()],
                MigrateOrderMetaToHpos::ACTION_GROUP
            ),
            'The next batch should start after the last order of this one'
        );
    }

    public function test_an_empty_batch_stops_scheduling()
    {
        if (! function_exists('as_has_scheduled_action')) {
            $this->markTestSkipped('Action Scheduler is not available');
        }

        $order = $this->createOrder();
        update_post_meta($order->get_id(), '_pushallowed', 'ok');

        MigrateOrderMetaToHpos::runBatch($order->get_id());

        $this->assertFalse(
            as_has_scheduled_action(
                MigrateOrderMetaToHpos::BATCH_HOOK,
                [$order->get_id()],
                MigrateOrderMetaToHpos::ACTION_GROUP
            ),
            'Nothing left to copy has to end the chain, not queue another batch'
        );
    }

    /** Drive the chain over more orders than one batch holds. */
    public function test_every_order_across_many_batches_is_migrated()
    {
        $count = (int) (MigrateOrderMetaToHpos::BATCH_SIZE * 2.5);
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $order = $this->createOrder();
            $ids[] = $order->get_id();
            update_post_meta($order->get_id(), '_pushallowed', 'ok');
            update_post_meta($order->get_id(), '_refundbuckarooTX' . $i, 'ok');
            add_post_meta($order->get_id(), '_wc_order_captures', ['id' => 'cap' . $i, 'amount' => '1.00']);
            update_post_meta($order->get_id(), 'buckaroo_settlement', ['TX' . $i => 1.0]);
        }

        // Walk the chain the way Action Scheduler would: each batch returns the cursor
        // for the next through the queued action's args.
        $cursor = min($ids) - 1;
        $batches = 0;

        do {
            as_unschedule_all_actions(MigrateOrderMetaToHpos::BATCH_HOOK);
            MigrateOrderMetaToHpos::runBatch($cursor);
            $batches++;

            $next = null;
            foreach (as_get_scheduled_actions([
                'hook' => MigrateOrderMetaToHpos::BATCH_HOOK,
                'group' => MigrateOrderMetaToHpos::ACTION_GROUP,
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'per_page' => 1,
            ]) as $action) {
                $args = $action->get_args();
                $next = (int) reset($args);
            }

            $progressed = $next !== null && $next > $cursor;
            $cursor = $next ?? $cursor;
        } while ($progressed && $batches < 20);

        $this->assertGreaterThan(2, $batches, 'The data should have needed more than one batch');

        $missed = [];
        foreach ($ids as $i => $id) {
            if (OrderMeta::get($id, '_pushallowed') !== 'ok'
                || OrderMeta::get($id, '_refundbuckarooTX' . $i) !== 'ok'
                || OrderMeta::get($id, 'buckaroo_settlement') !== ['TX' . $i => 1.0]
                || array_column(OrderMeta::get($id, '_wc_order_captures', false), 'id') !== ['cap' . $i]) {
                $missed[] = $id;
            }
        }

        $this->assertSame([], $missed, 'Every order in every batch has to be migrated');
    }

    public function test_the_batch_hook_is_wired_up_for_action_scheduler()
    {
        MigrateOrderMetaToHpos::registerBatchHandler();

        $this->assertNotFalse(
            has_action(MigrateOrderMetaToHpos::BATCH_HOOK, [MigrateOrderMetaToHpos::class, 'runBatch'])
        );
    }
}
