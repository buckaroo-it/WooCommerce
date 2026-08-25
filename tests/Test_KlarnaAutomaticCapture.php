<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaFulfillmentActions;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateOrderMetaToHpos;
use Buckaroo\Woocommerce\Order\KlarnaCaptureAttempt;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\OrderMeta;
use PHPUnit\Framework\TestCase;

class Test_KlarnaAutomaticCapture extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableHpos();
        delete_option('woocommerce_buckaroo_klarnapay_settings');
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        as_unschedule_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::QUEUE_CAPTURE_HOOK);
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
    }

    protected function tearDown(): void
    {
        as_unschedule_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::QUEUE_CAPTURE_HOOK);
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);

        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }

        delete_option('woocommerce_buckaroo_klarnapay_settings');
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        delete_option(KlarnaCaptureAttempt::NOTIFICATIONS_OPTION);
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_klarna_mor_exposes_a_default_off_automatic_capture_setting(): void
    {
        $gateway = new KlarnaPayGateway();

        $this->assertArrayHasKey('automatic_capture', $gateway->form_fields);
        $this->assertSame('checkbox', $gateway->form_fields['automatic_capture']['type']);
        $this->assertSame('no', $gateway->form_fields['automatic_capture']['default']);
        $this->assertSame('no', $gateway->get_option('automatic_capture'));
    }

    public function test_completed_transition_does_not_enqueue_when_automatic_capture_is_disabled(): void
    {
        $order = $this->createReservedOrder();
        new KlarnaFulfillmentActions();

        $order->update_status('completed');

        $this->assertFalse(
            as_has_scheduled_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK)
        );
        $this->assertSame([], KlarnaCaptureAttempt::all($order));
    }

    public function test_completed_transition_records_and_enqueues_one_eligible_capture(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $order = $this->createReservedOrder();
        new KlarnaFulfillmentActions();

        $order->update_status('completed');

        $attempts = KlarnaCaptureAttempt::all(wc_get_order($order->get_id()));
        $this->assertCount(1, $attempts);
        $this->assertSame('queued', $attempts[0]['state']);
        $this->assertSame('25.00', $attempts[0]['amount']);
        $this->assertSame(1, $attempts[0]['attempt_number']);
        $this->assertTrue(
            (bool) as_has_scheduled_action(
                KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK,
                [$order->get_id(), 1],
                KlarnaFulfillmentActions::ACTION_GROUP
            )
        );
    }

    public function test_background_hook_claims_and_captures_a_completed_order_once(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        $order = $this->createReservedOrder();
        $buckarooClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-AUTOMATIC'));
        new KlarnaFulfillmentActions($buckarooClient);
        $order->update_status('completed');

        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);

        $storedOrder = wc_get_order($order->get_id());
        $attempts = KlarnaCaptureAttempt::all($storedOrder);
        $captures = OrderMeta::get($storedOrder, '_wc_order_captures', false);
        $this->assertSame(1, $buckarooClient->sendCount);
        $this->assertSame('succeeded', $attempts[0]['state']);
        $this->assertSame('PAY-AUTOMATIC', $attempts[0]['transaction_key']);
        $this->assertCount(1, $captures);
        $this->assertSame('25.00', $captures[0]['amount']);
        $this->assertSame('completed', $storedOrder->get_status());
    }

    public function test_background_hook_captures_only_the_remaining_amount_after_a_partial_capture(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $order = $this->createReservedOrder(12.50, 2);
        $item = current($order->get_items('line_item'));
        OrderMeta::add($order, '_wc_order_captures', [
            'id' => 'existing',
            'amount' => '12.50',
            'currency' => 'EUR',
            'line_item_qtys' => wp_json_encode([$item->get_id() => 1]),
            'line_item_totals' => wp_json_encode([$item->get_id() => 12.50]),
            'line_item_tax_totals' => wp_json_encode([$item->get_id() => []]),
            'transaction_id' => 'PAY-EXISTING',
        ]);
        $buckarooClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-REMAINDER'));
        new KlarnaFulfillmentActions($buckarooClient);
        $order->update_status('completed');

        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);

        $storedOrder = wc_get_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::find($storedOrder, 1);
        $captures = OrderMeta::get($storedOrder, '_wc_order_captures', false);
        $this->assertSame(1, $buckarooClient->sendCount);
        $this->assertSame('succeeded', $attempt['state'], $attempt['last_error']);
        $this->assertSame('12.50', $buckarooClient->payload['amountDebit']);
        $this->assertSame(1, $buckarooClient->payload['articles'][0]['quantity']);
        $this->assertCount(2, $captures);
        $this->assertSame('PAY-REMAINDER', $captures[1]['transaction_id']);
    }

    public function test_only_reserved_klarna_mor_orders_with_a_data_request_key_are_eligible(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        new KlarnaFulfillmentActions();

        $unrelated = $this->createReservedOrder();
        $unrelated->set_payment_method('buckaroo_klarnakp');
        $unrelated->save();
        $unrelated->update_status('completed');

        $notReserved = $this->createReservedOrder();
        $notReserved->delete_meta_data('buckaroo_is_reserved');
        $notReserved->save();
        $notReserved->update_status('completed');

        $missingKey = $this->createReservedOrder();
        $missingKey->delete_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY);
        $missingKey->save();
        $missingKey->update_status('completed');

        $this->assertSame([], KlarnaCaptureAttempt::all(wc_get_order($unrelated->get_id())));
        $this->assertSame([], KlarnaCaptureAttempt::all(wc_get_order($notReserved->get_id())));
        $this->assertSame([], KlarnaCaptureAttempt::all(wc_get_order($missingKey->get_id())));
        $this->assertFalse((bool) as_has_scheduled_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK));
    }

    public function test_repeated_completed_transitions_keep_one_queued_attempt(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $order = $this->createReservedOrder();
        new KlarnaFulfillmentActions();

        $order->update_status('completed');
        $order->save();
        $order->update_status('pending');
        $order->update_status('completed');

        $this->assertCount(1, KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
        $actions = as_get_scheduled_actions([
            'hook' => KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK,
            'status' => ActionScheduler_Store::STATUS_PENDING,
        ]);
        $this->assertCount(1, $actions);
    }

    public function test_completed_transition_requeues_after_attempt_ledger_lock_contention(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'no']);
        $order = $this->createReservedOrder();
        $order->set_status('completed');
        $order->save();
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $actions = new KlarnaFulfillmentActions();
        $lockName = 'buckaroo_attempt_ledger_' . substr(
            hash('sha256', $order->get_id() . ':all'),
            0,
            40
        );
        $blocker = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $this->assertSame('1', (string) $blocker->get_var(
            $blocker->prepare('SELECT GET_LOCK(%s, 0)', $lockName)
        ));

        try {
            $actions->handle_completed_order($order->get_id());
        } finally {
            $blocker->get_var($blocker->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }

        $queueHook = KlarnaFulfillmentActions::QUEUE_CAPTURE_HOOK;
        $this->assertTrue((bool) as_has_scheduled_action(
            $queueHook,
            [$order->get_id(), 1],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));

        do_action($queueHook, $order->get_id(), 1);

        $this->assertCount(1, KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
        $this->assertTrue((bool) as_has_scheduled_action(
            KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK,
            [$order->get_id(), 1],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));
    }

    public function test_completed_transition_heals_an_orphaned_allocation_claim(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'no']);
        $order = $this->createReservedOrder();
        $order->set_status('completed');
        $order->save();
        $allocation = CaptureAllocation::remainingForOrder($order, 25.00);
        $claimKey = '_buckaroo_klarna_capture_' . $order->get_id() . '_' . substr(
            $allocation->fingerprint(),
            0,
            32
        );
        add_option($claimKey, ['state' => 'queued'], '', 'no');
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);

        (new KlarnaFulfillmentActions())->handle_completed_order($order->get_id());

        $attempts = KlarnaCaptureAttempt::all(wc_get_order($order->get_id()));
        $this->assertCount(1, $attempts);
        $this->assertSame('queued', $attempts[0]['state']);
        $this->assertSame(1, get_option($claimKey)['attempt_number']);
        $this->assertTrue((bool) as_has_scheduled_action(
            KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK,
            [$order->get_id(), 1],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));
    }

    public function test_attempt_ledger_contention_exhaustion_creates_admin_attention(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'no']);
        $order = $this->createReservedOrder();
        $order->set_status('completed');
        $order->save();
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $actions = new KlarnaFulfillmentActions();
        $lockName = 'buckaroo_attempt_ledger_' . substr(
            hash('sha256', $order->get_id() . ':all'),
            0,
            40
        );
        $blocker = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $this->assertSame('1', (string) $blocker->get_var(
            $blocker->prepare('SELECT GET_LOCK(%s, 0)', $lockName)
        ));

        try {
            $actions->handle_completed_order($order->get_id(), 3);
        } finally {
            $blocker->get_var($blocker->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }

        $this->assertArrayHasKey($order->get_id(), KlarnaCaptureAttempt::notifications());
        $this->assertSame([], KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
    }

    public function test_worker_skips_when_the_order_is_no_longer_eligible(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $mutations = [
            static function (WC_Order $order): void {
                $order->set_status('processing');
            },
            static function (WC_Order $order): void {
                $order->set_payment_method('buckaroo_klarnakp');
            },
            static function (WC_Order $order): void {
                $order->delete_meta_data('buckaroo_is_reserved');
            },
            static function (WC_Order $order): void {
                $order->delete_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY);
            },
            static function (WC_Order $order): void {
                update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'no']);
            },
        ];

        foreach ($mutations as $mutation) {
            update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
            $order = $this->createReservedOrder();
            $client = new InMemoryBuckarooClient($this->successfulResponse('PAY-MUST-NOT-SEND'));
            $actions = new KlarnaFulfillmentActions($client);
            $order->set_status('completed');
            $order->save();
            $actions->handle_completed_order($order->get_id());
            $mutation($order);
            $order->save();

            $actions->handle_automatic_capture($order->get_id(), 1);

            $attempt = KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1);
            $this->assertSame('skipped', $attempt['state']);
            $this->assertNotSame('', $attempt['last_error']);
            $this->assertSame(0, $client->sendCount);
        }
    }

    public function test_zero_remaining_capture_is_a_no_op(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $order = $this->createReservedOrder();
        $item = current($order->get_items('line_item'));
        $this->addCapture($order, 'full', 'PAY-FULL', $item->get_id(), 1, 25.00);
        $actions = new KlarnaFulfillmentActions();
        $order->set_status('completed');
        $order->save();

        $actions->handle_completed_order($order->get_id());
        $actions->handle_completed_order($order->get_id());

        $this->assertSame([], KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
        $this->assertFalse((bool) as_has_scheduled_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK));
    }

    public function test_completed_transition_migrates_legacy_capture_rows_before_calculating_remaining(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        delete_option(MigrateOrderMetaToHpos::COMPLETE_OPTION);
        $order = $this->createReservedOrder(10.00, 2);
        $item = current($order->get_items('line_item'));
        add_post_meta($order->get_id(), '_wc_order_captures', [
            'id' => 'legacy-partial',
            'amount' => '10.00',
            'currency' => 'EUR',
            'line_item_qtys' => wp_json_encode([$item->get_id() => 1]),
            'line_item_totals' => wp_json_encode([$item->get_id() => 10.00]),
            'line_item_tax_totals' => wp_json_encode([$item->get_id() => []]),
            'transaction_id' => 'PAY-LEGACY',
        ]);
        $order->set_status('completed');
        $order->save();

        (new KlarnaFulfillmentActions())->handle_completed_order($order->get_id());

        $attempt = KlarnaCaptureAttempt::latest(wc_get_order($order->get_id()));
        $this->assertSame('10.00', $attempt['amount']);
        $this->assertSame(1, $attempt['allocation']['line_item_qtys'][$item->get_id()]);
    }

    public function test_failed_enqueue_records_attention_and_allows_a_safe_retry(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'no']);
        $order = $this->createReservedOrder();
        $actions = new FailingEnqueueKlarnaFulfillmentActions();
        $order->set_status('completed');
        $order->save();
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);

        $actions->handle_completed_order($order->get_id());
        $storedOrder = wc_get_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::latest($storedOrder);
        $this->assertSame('failed', $attempt['state']);
        $this->assertStringContainsString('scheduled', strtolower($attempt['last_error']));
        $this->assertTrue(KlarnaCaptureAttempt::canRetry($storedOrder));
        $this->assertArrayHasKey($order->get_id(), KlarnaCaptureAttempt::notifications());
    }

    public function test_a_fully_captured_and_refunded_transaction_does_not_restore_capture_capacity(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $order = $this->createReservedOrder();
        $item = current($order->get_items('line_item'));
        OrderMeta::add($order, '_wc_order_captures', [
            'id' => 'full-capture',
            'amount' => '25.00',
            'currency' => 'EUR',
            'line_item_qtys' => wp_json_encode([$item->get_id() => 1]),
            'line_item_totals' => wp_json_encode([$item->get_id() => 25.00]),
            'line_item_tax_totals' => wp_json_encode([$item->get_id() => []]),
            'transaction_id' => 'PAY-FULL',
        ]);
        OrderMeta::update($order, 'buckaroo_captures_refunded', wp_json_encode(['full-capture']));
        new KlarnaFulfillmentActions();

        $order->update_status('completed');

        $this->assertSame([], KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
        $this->assertFalse((bool) as_has_scheduled_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK));
    }

    public function test_order_edits_after_reservation_are_capped_at_the_reserved_amount(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $order = $this->createReservedOrder();
        $this->addProduct($order, 10.00, 1, 'Added after reservation');
        $buckarooClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-RESERVED-CAP'));
        new KlarnaFulfillmentActions($buckarooClient);
        $order->update_status('completed');

        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);

        $this->assertSame('25.00', $buckarooClient->payload['amountDebit']);
        $articleTotal = array_sum(array_map(
            static function ($article) {
                return $article['price'] * $article['quantity'];
            },
            $buckarooClient->payload['articles']
        ));
        $this->assertSame(25.00, $articleTotal);
    }

    public function test_remaining_accounting_matches_in_classic_order_storage(): void
    {
        $this->disableHpos();
        $order = $this->createReservedOrder(10.00, 3);
        $item = current($order->get_items('line_item'));
        $this->addCapture($order, 'first', 'PAY-FIRST', $item->get_id(), 1, 10.00);
        $this->addCapture($order, 'second', 'PAY-SECOND', $item->get_id(), 1, 10.00);

        $remaining = CaptureAllocation::remainingForOrder($order, 30.00);

        $this->assertSame(10.00, $remaining->getAmount());
        $this->assertSame(1, $remaining->getQuantity($item->get_id()));
        $this->assertSame(10.00, $remaining->getTotal($item->get_id()));
    }

    public function test_automatic_allocation_covers_discounts_tax_shipping_fees_and_zero_price_lines(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $order = $this->createReservedOrder(12.00, 2);
        $productItem = current($order->get_items('line_item'));
        $productItem->set_subtotal('24.00');
        $productItem->set_total('20.00');
        $productItem->set_taxes([
            'subtotal' => [1 => '2.52'],
            'total' => [1 => '2.10'],
        ]);
        $productItem->save();
        $this->addProduct($order, 0.00, 1, 'Free item');

        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title('Tracked shipping');
        $shipping->set_method_id('flat_rate');
        $shipping->set_total('5.00');
        $order->add_item($shipping);

        $fee = new WC_Order_Item_Fee();
        $fee->set_name('Handling');
        $fee->set_amount('2.50');
        $fee->set_total('2.50');
        $order->add_item($fee);
        $order->update_taxes();
        $order->calculate_totals(false);
        $order->update_meta_data(
            KlarnaProcessor::RESERVED_AMOUNT_META_KEY,
            number_format((float) $order->get_total('edit'), 2, '.', '')
        );
        $order->save();

        $buckarooClient = new InMemoryBuckarooClient($this->successfulResponse('PAY-COMPLEX'));
        new KlarnaFulfillmentActions($buckarooClient);
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);

        $articleTotal = array_sum(array_map(
            static function ($article) {
                return $article['price'] * $article['quantity'];
            },
            $buckarooClient->payload['articles']
        ));
        $this->assertSame((float) $buckarooClient->payload['amountDebit'], $articleTotal);
        $this->assertNotContains(0.00, array_column($buckarooClient->payload['articles'], 'price'));
        $captures = OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false);
        $storedTotals = json_decode($captures[0]['line_item_totals'], true);
        $storedTaxes = json_decode($captures[0]['line_item_tax_totals'], true);
        $this->assertArrayHasKey($productItem->get_id(), $storedTaxes);
        $this->assertContains(0, $storedTotals);
    }

    private function successfulResponse(string $transactionKey): \BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse
    {
        $response = $this->getMockBuilder(\BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse::class)
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

    private function createReservedOrder(float $unitPrice = 25.00, int $quantity = 1): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Automatic capture product');
        $product->set_regular_price((string) $unitPrice);
        $product->set_price((string) $unitPrice);
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, $quantity);
        $order->set_payment_method('buckaroo_klarnapay');
        $order->set_currency('EUR');
        $order->calculate_totals();
        $order->update_meta_data('buckaroo_is_reserved', 'yes');
        $order->update_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY, 'DATA-REQUEST-AUTO');
        $order->update_meta_data(
            KlarnaProcessor::RESERVED_AMOUNT_META_KEY,
            number_format((float) $order->get_total('edit'), 2, '.', '')
        );
        $order->save();

        return wc_get_order($order->get_id());
    }

    private function addProduct(WC_Order $order, float $unitPrice, int $quantity, string $name): void
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price((string) $unitPrice);
        $product->set_price((string) $unitPrice);
        $product->save();
        $this->productIds[] = $product->get_id();

        $order->add_product($product, $quantity);
        $order->calculate_totals();
        $order->save();
    }

    private function addCapture(
        WC_Order $order,
        string $captureId,
        string $transactionId,
        int $itemId,
        int $quantity,
        float $amount
    ): void {
        OrderMeta::add($order, '_wc_order_captures', [
            'id' => $captureId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'EUR',
            'line_item_qtys' => wp_json_encode([$itemId => $quantity]),
            'line_item_totals' => wp_json_encode([$itemId => $amount]),
            'line_item_tax_totals' => wp_json_encode([$itemId => []]),
            'transaction_id' => $transactionId,
        ]);
    }
}

class FailingEnqueueKlarnaFulfillmentActions extends KlarnaFulfillmentActions
{
    protected function enqueueCapture(WC_Order $order, array $attempt)
    {
        return false;
    }
}
