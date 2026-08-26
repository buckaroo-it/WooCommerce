<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaFulfillmentActions;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaCaptureAttempt;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPushProcessor;
use Buckaroo\Woocommerce\ResponseParser\FormDataParser;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

class Test_KlarnaCapturePushReconciliation extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableHpos();
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        as_unschedule_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK);
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
    }

    protected function tearDown(): void
    {
        as_unschedule_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK);
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        delete_option('woocommerce_buckaroo_klarnapay_settings');
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        delete_option(KlarnaCaptureAttempt::NOTIFICATIONS_OPTION);

        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }

        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_delayed_pay_success_resolves_an_unknown_attempt_once_on_a_completed_order(): void
    {
        $order = $this->createReservedOrder();
        new KlarnaFulfillmentActions(new ThrowingBuckarooClient());
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);
        $this->assertSame(
            'unknown',
            KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1)['state']
        );
        $order->update_status('pending');
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);
        $this->assertCount(1, KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
        $this->assertSame(1, ThrowingBuckarooClient::$sendCount);

        $push = new FormDataParser([
            'brq_action' => 'Pay',
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-DELAYED',
            'brq_transaction_method' => 'klarna',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);
        $processor = new KlarnaPushProcessor();

        $this->assertTrue($processor->reconcileCapture(wc_get_order($order->get_id()), $push));
        $this->assertTrue($processor->reconcileCapture(wc_get_order($order->get_id()), $push));

        $storedOrder = wc_get_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::find($storedOrder, 1);
        $captures = OrderMeta::get($storedOrder, '_wc_order_captures', false);
        $this->assertSame('succeeded', $attempt['state']);
        $this->assertSame('PAY-DELAYED', $attempt['transaction_key']);
        $this->assertCount(1, $captures);
        $this->assertSame('PAY-DELAYED', $captures[0]['transaction_id']);
        $this->assertSame(['PAY-DELAYED' => 25.00], OrderMeta::get($storedOrder, 'buckaroo_settlement'));
        $this->assertSame('completed', $storedOrder->get_status());
    }

    public function test_payment_on_hold_stays_pending_until_the_matching_pay_push_succeeds(): void
    {
        $order = $this->createReservedOrder();
        $buckarooClient = new InMemoryBuckarooClient(
            $this->transactionResponse(793, 'PAY-PENDING', 'Payment on hold')
        );
        new KlarnaFulfillmentActions($buckarooClient);
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);

        $pending = KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1);
        $this->assertSame('pending', $pending['state']);
        $this->assertSame('PAY-PENDING', $pending['transaction_key']);
        $this->assertSame([], OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));

        $pendingPush = new FormDataParser([
            'brq_action' => 'Pay',
            'brq_statuscode' => '793',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-PENDING',
            'brq_transaction_method' => 'klarna',
        ]);
        $this->assertTrue(apply_filters(
            'buckaroo_push_handled',
            false,
            wc_get_order($order->get_id()),
            $pendingPush
        ));
        $this->assertSame(
            'pending',
            KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1)['state']
        );
        $this->assertSame([], OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));

        $push = new FormDataParser([
            'brq_action' => 'Pay',
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-PENDING',
            'brq_transaction_method' => 'klarna',
        ]);
        $this->assertTrue(KlarnaPushProcessor::reconcileCapture(wc_get_order($order->get_id()), $push));

        $resolved = KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1);
        $this->assertSame('succeeded', $resolved['state']);
        $this->assertCount(1, OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));
    }

    public function test_klarna_reservation_push_state_is_owned_by_the_klarna_push_processor(): void
    {
        $order = $this->createReservedOrder();
        $order->delete_meta_data('buckaroo_is_reserved');
        $order->delete_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY);
        $order->delete_meta_data(KlarnaProcessor::RESERVED_AMOUNT_META_KEY);
        $order->save();
        $push = new FormDataParser([
            'brq_action' => 'Reserve',
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'RESERVE-PUSH',
            'brq_transaction_method' => 'klarna',
            'brq_datarequest' => 'DATA-REQUEST-FROM-PUSH',
        ]);

        new KlarnaFulfillmentActions();
        $context = apply_filters('buckaroo_push_reservation', null, $order, $push);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('DATA-REQUEST-FROM-PUSH', $context['transaction']);
        $this->assertFalse($context['completed_order']);
        $this->assertSame('yes', $storedOrder->get_meta('buckaroo_is_reserved'));
        $this->assertSame(
            'DATA-REQUEST-FROM-PUSH',
            $storedOrder->get_meta(KlarnaProcessor::DATA_REQUEST_META_KEY)
        );
        $this->assertSame('25.00', $storedOrder->get_meta(KlarnaProcessor::RESERVED_AMOUNT_META_KEY));
        $this->assertSame('on-hold', $storedOrder->get_status());
    }

    public function test_successful_pay_reconciliation_marks_a_manual_on_hold_capture_as_paid(): void
    {
        $order = $this->createReservedOrder();
        $order->set_status('on-hold');
        $order->save();
        $item = current($order->get_items('line_item'));
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 25.00],
            [$item->get_id() => []]
        );
        $attempt = KlarnaCaptureAttempt::startManual($order, $allocation);
        KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            (int) $attempt['attempt_number'],
            ['state' => 'pending', 'transaction_key' => 'PAY-MANUAL']
        );
        $push = new FormDataParser([
            'brq_action' => 'Pay',
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-MANUAL',
            'brq_transaction_method' => 'klarna',
        ]);

        $this->assertTrue(KlarnaPushProcessor::reconcileCapture(
            wc_get_order($order->get_id()),
            $push
        ));

        $storedOrder = wc_get_order($order->get_id());
        $this->assertTrue($storedOrder->is_paid());
        $this->assertSame('processing', $storedOrder->get_status());
    }

    public function test_partial_manual_pay_push_marks_the_order_paid_only_after_the_remaining_capture(): void
    {
        $order = $this->createReservedOrder(2);
        $order->set_status('on-hold');
        $order->save();
        $item = current($order->get_items('line_item'));
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 25.00],
            [$item->get_id() => []]
        );
        $processor = new KlarnaPushProcessor();

        $firstAttempt = KlarnaCaptureAttempt::startManual($order, $allocation);
        KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            (int) $firstAttempt['attempt_number'],
            ['state' => 'pending', 'transaction_key' => 'PAY-PARTIAL-ONE']
        );
        $this->assertTrue($processor->reconcileCapture(
            wc_get_order($order->get_id()),
            $this->payPush($order, 'PAY-PARTIAL-ONE', 25.00)
        ));

        $partiallyCapturedOrder = wc_get_order($order->get_id());
        $this->assertFalse($partiallyCapturedOrder->is_paid());
        $this->assertSame('on-hold', $partiallyCapturedOrder->get_status());
        $this->assertSame(
            '25.00',
            number_format((float) OrderMeta::get($partiallyCapturedOrder, '_wc_order_amount_captured'), 2, '.', '')
        );

        $secondAttempt = KlarnaCaptureAttempt::startManual($partiallyCapturedOrder, $allocation);
        KlarnaCaptureAttempt::updateUnlessSucceeded(
            $partiallyCapturedOrder,
            (int) $secondAttempt['attempt_number'],
            ['state' => 'pending', 'transaction_key' => 'PAY-PARTIAL-TWO']
        );
        $this->assertTrue($processor->reconcileCapture(
            wc_get_order($order->get_id()),
            $this->payPush($order, 'PAY-PARTIAL-TWO', 25.00)
        ));

        $fullyCapturedOrder = wc_get_order($order->get_id());
        $this->assertTrue($fullyCapturedOrder->is_paid());
        $this->assertSame('processing', $fullyCapturedOrder->get_status());
    }

    public function test_claim_releases_its_worker_option_when_the_attempt_ledger_is_locked(): void
    {
        $order = $this->createReservedOrder();
        $actions = new KlarnaFulfillmentActions();
        $order->set_status('completed');
        $order->save();
        $actions->handle_completed_order($order->get_id());
        $storedOrder = wc_get_order($order->get_id());
        $lockName = 'buckaroo_attempt_ledger_' . substr(
            hash('sha256', $order->get_id() . ':all'),
            0,
            40
        );
        $blocker = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $this->assertSame('1', (string) $blocker->get_var(
            $blocker->prepare('SELECT GET_LOCK(%s, 0)', $lockName)
        ));

        as_unschedule_action(
            KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK,
            [$order->get_id(), 1],
            KlarnaFulfillmentActions::ACTION_GROUP
        );
        $actions->handle_automatic_capture($order->get_id(), 1);
        $this->assertSame('queued', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertTrue((bool) as_has_scheduled_action(
            KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK,
            [$order->get_id(), 1, 1],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));
        $blocker->get_var($blocker->prepare('SELECT RELEASE_LOCK(%s)', $lockName));

        $claimed = KlarnaCaptureAttempt::claim($storedOrder, 1);
        $this->assertSame('in_progress', $claimed['state']);
    }

    public function test_worker_lock_contention_stops_after_the_bounded_retry_limit(): void
    {
        $order = $this->createReservedOrder();
        $actions = new KlarnaFulfillmentActions();
        $order->set_status('completed');
        $order->save();
        $actions->handle_completed_order($order->get_id());
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
            $actions->handle_automatic_capture($order->get_id(), 1, 3);
        } finally {
            $blocker->get_var($blocker->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }

        $attempt = KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1);
        $this->assertSame('queued', $attempt['state']);
        $this->assertArrayHasKey($order->get_id(), KlarnaCaptureAttempt::notifications());
        $this->assertTrue((bool) as_has_scheduled_action(
            KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK,
            [$order->get_id(), 1],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));

        do_action(KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK, $order->get_id(), 1);

        $this->assertSame(
            'failed',
            KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1)['state']
        );
    }

    public function test_success_push_records_reconciliation_attention_when_local_recording_times_out(): void
    {
        $order = $this->createReservedOrder();
        $item = current($order->get_items('line_item'));
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 25.00],
            [$item->get_id() => []]
        );
        $attempt = KlarnaCaptureAttempt::startManual($order, $allocation);
        KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            (int) $attempt['attempt_number'],
            ['state' => 'pending', 'transaction_key' => 'PAY-RECORD-TIMEOUT']
        );
        $lockName = 'buckaroo_capture_record_' . substr(
            hash('sha256', $order->get_id() . ':order'),
            0,
            40
        );
        $blocker = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $this->assertSame('1', (string) $blocker->get_var(
            $blocker->prepare('SELECT GET_LOCK(%s, 0)', $lockName)
        ));

        try {
            $this->assertTrue(KlarnaPushProcessor::reconcileCapture(
                wc_get_order($order->get_id()),
                $this->payPush($order, 'PAY-RECORD-TIMEOUT', 25.00)
            ));
        } finally {
            $blocker->get_var($blocker->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }

        $storedOrder = wc_get_order($order->get_id());
        $storedAttempt = KlarnaCaptureAttempt::find($storedOrder, (int) $attempt['attempt_number']);
        $this->assertSame('unknown', $storedAttempt['state']);
        $this->assertSame('PAY-RECORD-TIMEOUT', $storedAttempt['transaction_key']);
        $this->assertArrayHasKey($order->get_id(), KlarnaCaptureAttempt::notifications());
        $this->assertSame([], OrderMeta::get($storedOrder, '_wc_order_captures', false));
    }

    public function test_a_definite_rejection_is_failed_with_its_error_and_keeps_completed(): void
    {
        $order = $this->createReservedOrder();
        $buckarooClient = new InMemoryBuckarooClient(
            $this->transactionResponse(490, 'PAY-REJECTED', 'Capture declined')
        );
        new KlarnaFulfillmentActions($buckarooClient);
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);

        $storedOrder = wc_get_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::find($storedOrder, 1);
        $this->assertSame('failed', $attempt['state']);
        $this->assertStringContainsString('Capture declined', $attempt['last_error']);
        $this->assertSame([], OrderMeta::get($storedOrder, '_wc_order_captures', false));
        $this->assertSame('completed', $storedOrder->get_status());
    }

    public function test_out_of_order_failure_cannot_regress_success_and_other_actions_do_not_reconcile(): void
    {
        $order = $this->createReservedOrder();
        $buckarooClient = new InMemoryBuckarooClient(
            $this->transactionResponse(190, 'PAY-SUCCEEDED', '')
        );
        new KlarnaFulfillmentActions($buckarooClient);
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);
        $processor = new KlarnaPushProcessor();

        $failedPay = new FormDataParser([
            'brq_action' => 'Pay',
            'brq_statuscode' => '490',
            'brq_statusmessage' => 'Late failure',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-SUCCEEDED',
            'brq_transaction_method' => 'klarna',
        ]);
        $pendingPay = new FormDataParser([
            'brq_action' => 'Pay',
            'brq_statuscode' => '793',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-SUCCEEDED',
            'brq_transaction_method' => 'klarna',
        ]);
        $reserve = new FormDataParser([
            'brq_action' => 'Reserve',
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'RESERVE-NEW',
            'brq_transaction_method' => 'klarna',
        ]);
        $cancel = new FormDataParser([
            'brq_action' => 'CancelReservation',
            'brq_statuscode' => '190',
            'brq_transactions' => 'CANCEL-NEW',
            'brq_transaction_method' => 'klarna',
        ]);

        $this->assertTrue($processor->reconcileCapture(wc_get_order($order->get_id()), $pendingPay));
        $this->assertSame(
            'succeeded',
            KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1)['state']
        );
        $this->assertTrue($processor->reconcileCapture(wc_get_order($order->get_id()), $failedPay));
        $this->assertFalse($processor->reconcileCapture(wc_get_order($order->get_id()), $reserve));
        $this->assertFalse($processor->reconcileCapture(wc_get_order($order->get_id()), $cancel));

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('succeeded', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertCount(1, OrderMeta::get($storedOrder, '_wc_order_captures', false));
    }

    public function test_same_amount_push_with_a_different_key_does_not_match_a_known_attempt(): void
    {
        $order = $this->createReservedOrder();
        $client = new InMemoryBuckarooClient($this->transactionResponse(793, 'PAY-KNOWN', 'On hold'));
        $actions = new KlarnaFulfillmentActions($client);
        $order->set_status('completed');
        $order->save();
        $actions->handle_completed_order($order->get_id());
        $actions->handle_automatic_capture($order->get_id(), 1);
        $push = new FormDataParser([
            'brq_action' => 'Pay',
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-DIFFERENT',
            'brq_transaction_method' => 'klarna',
        ]);

        $this->assertFalse(KlarnaPushProcessor::reconcileCapture(
            wc_get_order($order->get_id()),
            $push
        ));
        $attempt = KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1);
        $this->assertSame('pending', $attempt['state']);
        $this->assertSame('PAY-KNOWN', $attempt['transaction_key']);
    }

    public function test_real_c339_push_without_action_wins_over_worker_response(): void
    {
        $order = $this->createReservedOrder();
        $push = new FormDataParser([
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-INTERLEAVED',
            'brq_transaction_method' => 'klarna',
            'brq_transaction_type' => 'C339',
        ]);
        $client = new InterleavingBuckarooClient(
            function () use ($order, $push): void {
                $this->assertTrue(apply_filters(
                    'buckaroo_push_handled',
                    false,
                    wc_get_order($order->get_id()),
                    $push
                ));
            },
            $this->transactionResponse(490, 'PAY-INTERLEAVED', 'Late response failure')
        );
        $actions = new KlarnaFulfillmentActions($client);
        $order->set_status('completed');
        $order->save();
        $actions->handle_completed_order($order->get_id());

        $actions->handle_automatic_capture($order->get_id(), 1);

        $storedOrder = wc_get_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::find($storedOrder, 1);
        $captures = OrderMeta::get($storedOrder, '_wc_order_captures', false);
        $this->assertSame('succeeded', $attempt['state']);
        $this->assertSame('', $attempt['last_error']);
        $this->assertSame('completed', $storedOrder->get_status());
        $this->assertCount(1, $captures);
        $this->assertSame('PAY-INTERLEAVED', $captures[0]['transaction_id']);
        $this->assertSame(
            ['PAY-INTERLEAVED' => 25.00],
            OrderMeta::get($storedOrder, 'buckaroo_settlement')
        );
    }

    public function test_real_c339_push_matches_the_active_retry_over_an_older_failed_attempt(): void
    {
        $order = $this->createReservedOrder();
        $item = current($order->get_items('line_item'));
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 25.00],
            [$item->get_id() => []]
        );
        $actions = new KlarnaFulfillmentActions();
        $order->set_status('completed');
        $order->save();
        $actions->handle_completed_order($order->get_id());
        KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            1,
            ['state' => 'failed', 'transaction_key' => null]
        );
        $retry = KlarnaCaptureAttempt::retry($order, $allocation);
        KlarnaCaptureAttempt::claim($order, (int) $retry['attempt_number']);
        $push = new FormDataParser([
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-RETRY',
            'brq_transaction_method' => 'klarna',
            'brq_transaction_type' => 'C339',
        ]);

        $this->assertTrue(KlarnaPushProcessor::reconcileCapture(
            wc_get_order($order->get_id()),
            $push
        ));

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('failed', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertSame('succeeded', KlarnaCaptureAttempt::find($storedOrder, 2)['state']);
        $this->assertCount(1, OrderMeta::get($storedOrder, '_wc_order_captures', false));
    }

    public function test_actionless_non_pay_klarna_push_does_not_reconcile_a_capture(): void
    {
        $order = $this->createReservedOrder();
        $actions = new KlarnaFulfillmentActions();
        $order->set_status('completed');
        $order->save();
        $actions->handle_completed_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::claim($order, 1);
        $push = new FormDataParser([
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'NOT-A-PAY',
            'brq_transaction_method' => 'klarna',
            'brq_transaction_type' => 'C340',
        ]);

        try {
            $this->assertFalse(KlarnaPushProcessor::handle(
                false,
                wc_get_order($order->get_id()),
                $push
            ));
            $this->assertSame(
                'in_progress',
                KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1)['state']
            );
            $this->assertSame([], OrderMeta::get($order, '_wc_order_captures', false));
        } finally {
            KlarnaCaptureAttempt::releaseWorkerClaim($order, 1);
            KlarnaCaptureAttempt::releaseClaim($order, $attempt['allocation_fingerprint']);
        }
    }

    public function test_success_push_retries_when_the_attempt_ledger_is_locked(): void
    {
        $order = $this->createReservedOrder();
        $actions = new KlarnaFulfillmentActions();
        $order->set_status('completed');
        $order->save();
        $actions->handle_completed_order($order->get_id());
        KlarnaCaptureAttempt::claim($order, 1);
        $push = new FormDataParser([
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-ATTEMPT-LOCK',
            'brq_transaction_method' => 'klarna',
            'brq_transaction_type' => 'C339',
        ]);
        $lockName = 'buckaroo_attempt_ledger_' . substr(
            hash('sha256', $order->get_id() . ':all'),
            0,
            40
        );
        $blocker = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $this->assertSame('1', (string) $blocker->get_var(
            $blocker->prepare('SELECT GET_LOCK(%s, 0)', $lockName)
        ));

        $thrown = false;
        try {
            KlarnaPushProcessor::reconcileCapture(wc_get_order($order->get_id()), $push);
        } catch (RuntimeException $exception) {
            $thrown = true;
        } finally {
            $blocker->get_var($blocker->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }

        $this->assertTrue($thrown);
        $this->assertSame([], OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));
        $this->assertTrue(KlarnaPushProcessor::reconcileCapture(
            wc_get_order($order->get_id()),
            $push
        ));
        $this->assertSame(
            'succeeded',
            KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1)['state']
        );
        $this->assertCount(1, OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));
    }

    public function test_success_push_retries_when_settlement_recording_is_locked(): void
    {
        $order = $this->createReservedOrder();
        $item = current($order->get_items('line_item'));
        $allocation = CaptureAllocation::fromArrays(
            [$item->get_id() => 1],
            [$item->get_id() => 25.00],
            [$item->get_id() => []]
        );
        $attempt = KlarnaCaptureAttempt::startManual($order, $allocation);
        KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            (int) $attempt['attempt_number'],
            ['state' => 'pending', 'transaction_key' => 'PAY-SETTLEMENT-LOCK']
        );
        $push = new FormDataParser([
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PAY-SETTLEMENT-LOCK',
            'brq_transaction_method' => 'klarna',
            'brq_transaction_type' => 'C339',
        ]);
        $lockName = 'buckaroo_settlement_' . substr(
            hash('sha256', $order->get_id() . ':order'),
            0,
            40
        );
        $blocker = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $this->assertSame('1', (string) $blocker->get_var(
            $blocker->prepare('SELECT GET_LOCK(%s, 0)', $lockName)
        ));

        $thrown = false;
        try {
            KlarnaPushProcessor::reconcileCapture(wc_get_order($order->get_id()), $push);
        } catch (RuntimeException $exception) {
            $thrown = true;
        } finally {
            $blocker->get_var($blocker->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }

        $storedOrder = wc_get_order($order->get_id());
        $this->assertTrue($thrown);
        $this->assertCount(1, OrderMeta::get($storedOrder, '_wc_order_captures', false));
        $this->assertEmpty(OrderMeta::get($storedOrder, 'buckaroo_settlement'));
        $this->assertTrue(KlarnaPushProcessor::reconcileCapture($storedOrder, $push));
        $this->assertCount(1, OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));
        $this->assertSame(
            ['PAY-SETTLEMENT-LOCK' => 25.00],
            OrderMeta::get(wc_get_order($order->get_id()), 'buckaroo_settlement')
        );
    }

    public function test_stale_worker_recovery_records_attention_and_a_single_order_note(): void
    {
        $order = $this->createReservedOrder();
        $actions = new KlarnaFulfillmentActions();
        $order->set_status('completed');
        $order->save();
        $actions->handle_completed_order($order->get_id());
        $storedOrder = wc_get_order($order->get_id());
        KlarnaCaptureAttempt::claim($storedOrder, 1);
        $attempts = KlarnaCaptureAttempt::all($storedOrder);
        $attempts[0]['updated_at'] = gmdate('c', time() - HOUR_IN_SECONDS);
        OrderMeta::update($storedOrder, KlarnaCaptureAttempt::META_KEY, $attempts);

        $actions->handle_recover_capture($order->get_id(), 1);
        $actions->handle_recover_capture($order->get_id(), 1);

        $recovered = KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1);
        $this->assertSame('unknown', $recovered['state']);
        $this->assertArrayHasKey($order->get_id(), KlarnaCaptureAttempt::notifications());
        $notes = array_filter(
            wc_get_order_notes(['order_id' => $order->get_id()]),
            static function ($note): bool {
                return strpos($note->content, 'outcome is unknown') !== false;
            }
        );
        $this->assertCount(1, $notes);
    }

    private function transactionResponse(int $statusCode, string $transactionKey, string $error): TransactionResponse
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
        $response->method('isSuccess')->willReturn($statusCode === 190);
        $response->method('isPendingProcessing')->willReturn($statusCode === 791);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getTransactionKey')->willReturn($transactionKey);
        $response->method('getSomeError')->willReturn($error);
        $response->method('toArray')->willReturn(['Key' => $transactionKey]);

        return $response;
    }

    private function payPush(WC_Order $order, string $transactionKey, float $amount): FormDataParser
    {
        return new FormDataParser([
            'brq_action' => 'Pay',
            'brq_statuscode' => '190',
            'brq_amount' => number_format($amount, 2, '.', ''),
            'brq_currency' => 'EUR',
            'brq_transactions' => $transactionKey,
            'brq_transaction_method' => 'klarna',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);
    }

    private function createReservedOrder(int $quantity = 1): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Push reconciliation product');
        $product->set_regular_price('25.00');
        $product->set_price('25.00');
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, $quantity);
        $order->set_payment_method('buckaroo_klarnapay');
        $order->set_currency('EUR');
        $order->calculate_totals();
        $order->update_meta_data('buckaroo_is_reserved', 'yes');
        $order->update_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY, 'DATA-REQUEST-PUSH');
        $order->update_meta_data(
            KlarnaProcessor::RESERVED_AMOUNT_META_KEY,
            number_format(25.00 * $quantity, 2, '.', '')
        );
        $order->save();

        return wc_get_order($order->get_id());
    }
}

class ThrowingBuckarooClient extends BuckarooClient
{
    public static int $sendCount = 0;

    public function __construct()
    {
        self::$sendCount = 0;
    }

    public function process(AbstractProcessor $processor, array $additionalData = []): TransactionResponse
    {
        self::$sendCount++;
        throw new RuntimeException('Lost response after send');
    }
}

class InterleavingBuckarooClient extends BuckarooClient
{
    /** @var callable */
    private $duringSend;

    /** @var TransactionResponse */
    private $response;

    public function __construct(callable $duringSend, TransactionResponse $response)
    {
        $this->duringSend = $duringSend;
        $this->response = $response;
    }

    public function process(AbstractProcessor $processor, array $additionalData = []): TransactionResponse
    {
        ($this->duringSend)();

        return $this->response;
    }
}
