<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaCaptureAttempt;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaCaptureStatusCheck;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaFulfillmentActions;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use BuckarooDeps\GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;

class Test_KlarnaCaptureStatusCheck extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    /** @var int[] */
    private $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableHpos();
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        $this->unscheduleKlarnaActions();
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        $managerId = $this->createUser('administrator');
        get_user_by('id', $managerId)->add_cap('edit_shop_orders');
        wp_set_current_user($managerId);
    }

    protected function tearDown(): void
    {
        $this->unscheduleKlarnaActions();
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        delete_option('woocommerce_buckaroo_klarnapay_settings');
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        delete_option(KlarnaCaptureAttempt::NOTIFICATIONS_OPTION);
        wp_set_current_user(0);

        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }

        foreach ($this->userIds as $userId) {
            wp_delete_user($userId);
        }

        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_unknown_attempt_with_a_transaction_key_is_recorded_when_buckaroo_reports_success(): void
    {
        $order = $this->createUnknownAttempt('PAY-UNKNOWN');
        $client = new InMemoryBuckarooClient(
            $this->successfulResponse('PAY-MUST-NOT-SEND'),
            $this->statusResponse($order, 190, 'PAY-UNKNOWN', 'Transaction successfully processed')
        );
        $actions = new KlarnaFulfillmentActions($client);

        $actions->handle_check_capture($order);

        $storedOrder = wc_get_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::find($storedOrder, 1);
        $this->assertSame('succeeded', $attempt['state']);
        $this->assertSame('PAY-UNKNOWN', $attempt['transaction_key']);
        $this->assertSame(['PAY-UNKNOWN'], $client->statusRequests);
        $this->assertSame(0, $client->sendCount);

        $captures = OrderMeta::get($storedOrder, '_wc_order_captures', false);
        $this->assertCount(1, $captures);
        $this->assertSame('PAY-UNKNOWN', $captures[0]['transaction_id']);
        $this->assertSame('25.00', $captures[0]['amount']);
        $this->assertSame('completed', $storedOrder->get_status());
        $this->assertNotNull($storedOrder->get_date_paid());
        $this->assertArrayNotHasKey($order->get_id(), KlarnaCaptureAttempt::notifications());
        $this->assertArrayNotHasKey(
            'buckaroo_klarnapay_check_capture',
            $actions->add_fulfillment_actions([], $storedOrder)
        );
        $this->assertStringContainsString('is now succeeded', $this->lastNote($order));
    }

    public function test_unknown_attempt_with_a_transaction_key_becomes_retryable_when_buckaroo_reports_a_definite_failure(): void
    {
        $order = $this->createUnknownAttempt('PAY-UNKNOWN');
        $client = new InMemoryBuckarooClient(
            $this->successfulResponse('PAY-MUST-NOT-SEND'),
            $this->statusResponse($order, 490, 'PAY-UNKNOWN', 'Capture declined')
        );
        $actions = new KlarnaFulfillmentActions($client);

        $actions->handle_check_capture($order);

        $storedOrder = wc_get_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::find($storedOrder, 1);
        $this->assertSame('failed', $attempt['state']);
        $this->assertStringContainsString('Capture declined', $attempt['last_error']);
        $this->assertSame([], OrderMeta::get($storedOrder, '_wc_order_captures', false));
        $this->assertSame(0, $client->sendCount);

        $availableActions = $actions->add_fulfillment_actions([], $storedOrder);
        $this->assertArrayHasKey('buckaroo_klarnapay_retry_capture', $availableActions);
        $this->assertArrayNotHasKey('buckaroo_klarnapay_check_capture', $availableActions);

        ob_start();
        KlarnaFulfillmentActions::handle_admin_notices();
        $notice = (string) ob_get_clean();
        $this->assertStringContainsString('Klarna capture failed', $notice);
        $this->assertStringContainsString('Capture declined', $notice);
    }

    public function test_unknown_attempt_with_a_transaction_key_stays_pending_while_buckaroo_is_still_processing(): void
    {
        $order = $this->createUnknownAttempt('PAY-UNKNOWN');
        $client = new InMemoryBuckarooClient(
            $this->successfulResponse('PAY-MUST-NOT-SEND'),
            $this->statusResponse($order, 791, 'PAY-UNKNOWN', 'Pending processing')
        );
        $actions = new KlarnaFulfillmentActions($client);

        $actions->handle_check_capture($order);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('pending', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertSame([], OrderMeta::get($storedOrder, '_wc_order_captures', false));
        $this->assertSame(0, $client->sendCount);

        $availableActions = $actions->add_fulfillment_actions([], $storedOrder);
        $this->assertArrayHasKey('buckaroo_klarnapay_check_capture', $availableActions);
        $this->assertArrayNotHasKey('buckaroo_klarnapay_retry_capture', $availableActions);
    }

    public function test_status_check_transport_error_changes_nothing(): void
    {
        $order = $this->createUnknownAttempt('PAY-UNKNOWN');
        $client = new InMemoryBuckarooClient($this->successfulResponse('PAY-MUST-NOT-SEND'));
        $client->statusException = new RuntimeException('Buckaroo unreachable');
        $actions = new KlarnaFulfillmentActions($client);

        $actions->handle_check_capture($order);

        $storedOrder = wc_get_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::find($storedOrder, 1);
        $this->assertSame('unknown', $attempt['state']);
        $this->assertSame('PAY-UNKNOWN', $attempt['transaction_key']);
        $this->assertSame([], OrderMeta::get($storedOrder, '_wc_order_captures', false));
        $this->assertSame(0, $client->sendCount);
        $this->assertStringContainsString('could not be completed', $this->lastNote($order));
        $this->assertStringContainsString('Buckaroo unreachable', $this->lastNote($order));
        $this->assertArrayHasKey(
            'buckaroo_klarnapay_check_capture',
            $actions->add_fulfillment_actions([], $storedOrder)
        );
    }

    public function test_unknown_attempt_without_a_transaction_key_waits_for_the_push_during_the_grace_period(): void
    {
        $order = $this->createUnknownAttempt(null);
        $client = new InMemoryBuckarooClient($this->successfulResponse('PAY-MUST-NOT-SEND'));
        $actions = new KlarnaFulfillmentActions($client);

        $actions->handle_check_capture($order);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('unknown', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertSame([], $client->statusRequests);
        $this->assertSame(0, $client->sendCount);
        $this->assertStringContainsString('Waiting for the Buckaroo push', $this->lastNote($order));
        $this->assertArrayNotHasKey(
            'buckaroo_klarnapay_retry_capture',
            $actions->add_fulfillment_actions([], $storedOrder)
        );
    }

    public function test_unknown_attempt_without_a_transaction_key_is_marked_failed_after_the_grace_period(): void
    {
        $order = $this->createUnknownAttempt(null);
        $this->backdateAttempt($order, 1, KlarnaCaptureStatusCheck::NO_KEY_GRACE_PERIOD + 60);
        $client = new InMemoryBuckarooClient($this->successfulResponse('PAY-MUST-NOT-SEND'));
        $actions = new KlarnaFulfillmentActions($client);

        $actions->handle_check_capture(wc_get_order($order->get_id()));

        $storedOrder = wc_get_order($order->get_id());
        $attempt = KlarnaCaptureAttempt::find($storedOrder, 1);
        $this->assertSame('failed', $attempt['state']);
        $this->assertStringContainsString('Buckaroo Plaza', $attempt['last_error']);
        $this->assertSame([], $client->statusRequests);
        $this->assertSame(0, $client->sendCount);
        $this->assertSame([], OrderMeta::get($storedOrder, '_wc_order_captures', false));
        $this->assertArrayHasKey(
            'buckaroo_klarnapay_retry_capture',
            $actions->add_fulfillment_actions([], $storedOrder)
        );

        ob_start();
        KlarnaFulfillmentActions::handle_admin_notices();
        $notice = (string) ob_get_clean();
        $this->assertStringContainsString('Klarna capture failed', $notice);
        $this->assertStringContainsString('Buckaroo Plaza', $notice);
    }

    public function test_check_action_is_only_offered_to_managers_for_unknown_or_pending_attempts(): void
    {
        $order = $this->createUnknownAttempt('PAY-UNKNOWN');
        $client = new InMemoryBuckarooClient(
            $this->successfulResponse('PAY-MUST-NOT-SEND'),
            $this->statusResponse($order, 190, 'PAY-UNKNOWN', 'Transaction successfully processed')
        );
        $actions = new KlarnaFulfillmentActions($client);
        $this->assertArrayHasKey('buckaroo_klarnapay_check_capture', $actions->add_fulfillment_actions([], $order));

        wp_set_current_user($this->createUser('subscriber'));
        $this->assertArrayNotHasKey('buckaroo_klarnapay_check_capture', $actions->add_fulfillment_actions([], $order));
        $actions->handle_check_capture($order);
        $this->assertSame([], $client->statusRequests);
        $this->assertSame('unknown', KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1)['state']);

        KlarnaCaptureAttempt::updateUnlessSucceeded($order, 1, ['state' => 'failed', 'last_error' => 'Declined']);
        wp_set_current_user($this->userIds[0]);
        $this->assertArrayNotHasKey(
            'buckaroo_klarnapay_check_capture',
            $actions->add_fulfillment_actions([], wc_get_order($order->get_id()))
        );
    }

    public function test_unknown_worker_outcome_schedules_one_status_check_that_resolves_the_attempt(): void
    {
        $order = $this->createUnknownAttempt(null);
        $this->assertTrue((bool) as_has_scheduled_action(
            KlarnaFulfillmentActions::CHECK_CAPTURE_HOOK,
            [$order->get_id(), 1, 0],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));

        KlarnaCaptureAttempt::updateUnlessSucceeded($order, 1, ['transaction_key' => 'PAY-UNKNOWN']);
        $this->assertTrue(KlarnaFulfillmentActions::scheduleStatusCheck(wc_get_order($order->get_id())));
        $this->assertCount(1, as_get_scheduled_actions([
            'hook' => KlarnaFulfillmentActions::CHECK_CAPTURE_HOOK,
            'args' => [$order->get_id(), 1, 0],
            'group' => KlarnaFulfillmentActions::ACTION_GROUP,
            'status' => ActionScheduler_Store::STATUS_PENDING,
        ]));

        $client = new InMemoryBuckarooClient(
            $this->successfulResponse('PAY-MUST-NOT-SEND'),
            $this->statusResponse($order, 190, 'PAY-UNKNOWN', 'Transaction successfully processed')
        );
        $actions = new KlarnaFulfillmentActions($client);

        $actions->handle_scheduled_check_capture($order->get_id(), 2);
        $this->assertSame([], $client->statusRequests);

        $actions->handle_scheduled_check_capture($order->get_id(), 1);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame(['PAY-UNKNOWN'], $client->statusRequests);
        $this->assertSame(0, $client->sendCount);
        $this->assertSame('succeeded', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertCount(1, OrderMeta::get($storedOrder, '_wc_order_captures', false));
    }

    public function test_manual_capture_with_an_unknown_outcome_schedules_a_status_check(): void
    {
        $order = $this->createReservedOrder();
        $order->update_status('on-hold');
        $client = $this->throwingClient('Connection lost');

        $result = (new KlarnaPayGateway())->capture(
            wc_get_order($order->get_id()),
            25.00,
            CaptureAllocation::forOrder(wc_get_order($order->get_id())),
            $client
        );

        $this->assertSame('unknown', $result->getStatus());
        $attempt = KlarnaCaptureAttempt::latest(wc_get_order($order->get_id()));
        $this->assertSame('manual', $attempt['source']);
        $this->assertSame('unknown', $attempt['state']);
        $this->assertTrue((bool) as_has_scheduled_action(
            KlarnaFulfillmentActions::CHECK_CAPTURE_HOOK,
            [$order->get_id(), 1, 0],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));

        $this->backdateAttempt($order, 1, KlarnaCaptureStatusCheck::NO_KEY_GRACE_PERIOD + 60);
        $actions = new KlarnaFulfillmentActions(new InMemoryBuckarooClient($this->successfulResponse('PAY-MUST-NOT-SEND')));
        $actions->handle_scheduled_check_capture($order->get_id(), 1);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('failed', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertStringContainsString('marked as failed', $this->lastNote($order));
        $this->assertNotNull(KlarnaCaptureAttempt::startManual(
            $storedOrder,
            CaptureAllocation::forOrder($storedOrder)
        ));
    }

    public function test_scheduled_check_retries_a_bounded_number_of_times_while_the_outcome_stays_open(): void
    {
        $order = $this->createUnknownAttempt('PAY-UNKNOWN');
        $client = new InMemoryBuckarooClient($this->successfulResponse('PAY-MUST-NOT-SEND'));
        $client->statusException = new RuntimeException('Buckaroo unreachable');
        $actions = new KlarnaFulfillmentActions($client);

        $actions->handle_scheduled_check_capture($order->get_id(), 1, 0);
        $this->assertTrue((bool) as_has_scheduled_action(
            KlarnaFulfillmentActions::CHECK_CAPTURE_HOOK,
            [$order->get_id(), 1, 1],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));

        $actions->handle_scheduled_check_capture($order->get_id(), 1, 2);
        $this->assertFalse((bool) as_has_scheduled_action(
            KlarnaFulfillmentActions::CHECK_CAPTURE_HOOK,
            [$order->get_id(), 1, 3],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));
        $this->assertSame(['PAY-UNKNOWN', 'PAY-UNKNOWN'], $client->statusRequests);
        $this->assertSame('unknown', KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1)['state']);
        $this->assertSame(0, $client->sendCount);
    }

    public function test_unknown_attempt_with_a_transaction_key_stays_pending_for_every_non_final_status(): void
    {
        foreach ([790, 792, 793, 794] as $statusCode) {
            $order = $this->createUnknownAttempt('PAY-UNKNOWN-' . $statusCode);
            $client = new InMemoryBuckarooClient(
                $this->successfulResponse('PAY-MUST-NOT-SEND'),
                $this->statusResponse($order, $statusCode, 'PAY-UNKNOWN-' . $statusCode, 'Not final')
            );
            $actions = new KlarnaFulfillmentActions($client);

            $actions->handle_check_capture($order);

            $storedOrder = wc_get_order($order->get_id());
            $this->assertSame('pending', KlarnaCaptureAttempt::find($storedOrder, 1)['state'], 'status ' . $statusCode);
            $this->assertSame([], OrderMeta::get($storedOrder, '_wc_order_captures', false));
            $this->assertArrayHasKey('buckaroo_klarnapay_check_capture', $actions->add_fulfillment_actions([], $storedOrder));
        }
    }

    public function test_pending_attempt_is_checkable_and_resolves_on_success(): void
    {
        $order = $this->createUnknownAttempt('PAY-PENDING');
        KlarnaCaptureAttempt::updateUnlessSucceeded($order, 1, ['state' => 'pending']);
        $client = new InMemoryBuckarooClient(
            $this->successfulResponse('PAY-MUST-NOT-SEND'),
            $this->statusResponse($order, 190, 'PAY-PENDING', 'Transaction successfully processed')
        );
        $actions = new KlarnaFulfillmentActions($client);
        $storedOrder = wc_get_order($order->get_id());
        $this->assertArrayHasKey('buckaroo_klarnapay_check_capture', $actions->add_fulfillment_actions([], $storedOrder));

        $actions->handle_check_capture($storedOrder);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('succeeded', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertCount(1, OrderMeta::get($storedOrder, '_wc_order_captures', false));
    }

    public function test_check_action_is_hidden_for_settled_queued_and_in_progress_attempts(): void
    {
        $order = $this->createUnknownAttempt('PAY-UNKNOWN');
        $actions = new KlarnaFulfillmentActions(new InMemoryBuckarooClient($this->successfulResponse('PAY-MUST-NOT-SEND')));

        foreach (['succeeded', 'skipped', 'queued', 'in_progress', 'failed'] as $state) {
            $this->overrideAttemptState($order, 1, $state);
            $this->assertArrayNotHasKey(
                'buckaroo_klarnapay_check_capture',
                $actions->add_fulfillment_actions([], wc_get_order($order->get_id())),
                'state ' . $state
            );
        }
    }

    public function test_status_response_that_does_not_match_the_attempt_changes_nothing(): void
    {
        $order = $this->createUnknownAttempt('PAY-UNKNOWN');
        $mismatch = $this->statusResponse($order, 190, 'PAY-UNKNOWN', 'Success');
        $data = $mismatch->toArray();
        $data['Currency'] = 'USD';
        $client = new InMemoryBuckarooClient(
            $this->successfulResponse('PAY-MUST-NOT-SEND'),
            new TransactionResponse(new PsrResponse(200), $data)
        );
        $actions = new KlarnaFulfillmentActions($client);

        $actions->handle_check_capture($order);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('unknown', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertSame([], OrderMeta::get($storedOrder, '_wc_order_captures', false));
        $this->assertStringContainsString('could not be matched', $this->lastNote($order));
    }

    public function test_grace_period_failure_never_overwrites_an_attempt_that_received_a_push(): void
    {
        $order = $this->createUnknownAttempt(null);
        $this->backdateAttempt($order, 1, KlarnaCaptureStatusCheck::NO_KEY_GRACE_PERIOD + 60);
        KlarnaCaptureAttempt::updateUnlessSucceeded(wc_get_order($order->get_id()), 1, [
            'state' => 'pending',
            'transaction_key' => 'PAY-LATE-PUSH',
        ]);

        $this->assertNull(KlarnaCaptureAttempt::failUnconfirmed(wc_get_order($order->get_id()), 1, 'stale'));
        $attempt = KlarnaCaptureAttempt::find(wc_get_order($order->get_id()), 1);
        $this->assertSame('pending', $attempt['state']);
        $this->assertSame('PAY-LATE-PUSH', $attempt['transaction_key']);
    }

    public function test_reconciliation_failure_after_success_clears_the_notice_instead_of_stranding_the_attempt(): void
    {
        $order = $this->createUnknownAttempt('PAY-UNKNOWN');
        $client = new InMemoryBuckarooClient(
            $this->successfulResponse('PAY-MUST-NOT-SEND'),
            $this->statusResponse($order, 190, 'PAY-UNKNOWN', 'Transaction successfully processed')
        );
        $actions = new KlarnaFulfillmentActions($client);
        $lockName = 'buckaroo_settlement_' . substr(hash('sha256', $order->get_id() . ':order'), 0, 40);
        $blocker = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $this->assertSame('1', (string) $blocker->get_var($blocker->prepare('SELECT GET_LOCK(%s, 0)', $lockName)));

        try {
            $actions->handle_check_capture($order);
        } finally {
            $blocker->get_var($blocker->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('succeeded', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertCount(1, OrderMeta::get($storedOrder, '_wc_order_captures', false));
        $this->assertArrayNotHasKey($order->get_id(), KlarnaCaptureAttempt::notifications());
        $this->assertStringContainsString('could not be completed', $this->lastNote($order));
    }

    public function test_an_older_unknown_attempt_is_still_checked_when_a_newer_attempt_exists(): void
    {
        $order = $this->createReservedOrder(2);
        $order->update_status('on-hold');
        $items = array_values($order->get_items());
        $first = CaptureAllocation::fromArrays(
            [$items[0]->get_id() => 1],
            [$items[0]->get_id() => 25.0],
            [$items[0]->get_id() => []]
        );
        $second = CaptureAllocation::fromArrays(
            [$items[1]->get_id() => 1],
            [$items[1]->get_id() => 25.0],
            [$items[1]->get_id() => []]
        );

        $gateway = new KlarnaPayGateway();
        $this->assertSame('unknown', $gateway->capture(wc_get_order($order->get_id()), 25.0, $first, $this->throwingClient('Connection lost'))->getStatus());
        $this->assertSame('succeeded', $gateway->capture(wc_get_order($order->get_id()), 25.0, $second, new InMemoryBuckarooClient($this->successfulResponse('PAY-SECOND')))->getStatus());
        KlarnaCaptureAttempt::updateUnlessSucceeded(wc_get_order($order->get_id()), 1, ['transaction_key' => 'PAY-FIRST']);

        $client = new InMemoryBuckarooClient(
            $this->successfulResponse('PAY-MUST-NOT-SEND'),
            $this->statusResponse($order, 190, 'PAY-FIRST', 'Transaction successfully processed', 25.0)
        );
        $actions = new KlarnaFulfillmentActions($client);
        $storedOrder = wc_get_order($order->get_id());
        $this->assertArrayHasKey('buckaroo_klarnapay_check_capture', $actions->add_fulfillment_actions([], $storedOrder));

        $actions->handle_check_capture($storedOrder);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame(['PAY-FIRST'], $client->statusRequests);
        $this->assertSame('succeeded', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);
        $this->assertSame('succeeded', KlarnaCaptureAttempt::find($storedOrder, 2)['state']);
        $this->assertCount(2, OrderMeta::get($storedOrder, '_wc_order_captures', false));
    }

    private function overrideAttemptState(WC_Order $order, int $attemptNumber, string $state): void
    {
        $order = wc_get_order($order->get_id());
        $attempts = KlarnaCaptureAttempt::all($order);
        foreach ($attempts as $index => $attempt) {
            if ((int) $attempt['attempt_number'] === $attemptNumber) {
                $attempts[$index]['state'] = $state;
            }
        }
        OrderMeta::update($order, KlarnaCaptureAttempt::META_KEY, $attempts);
    }

    private function createUnknownAttempt(?string $transactionKey): WC_Order
    {
        $order = $this->createReservedOrder();
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        new KlarnaFulfillmentActions($this->throwingClient('Connection lost'));
        $order->update_status('completed');
        do_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK, $order->get_id(), 1);
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);

        $storedOrder = wc_get_order($order->get_id());
        $this->assertSame('unknown', KlarnaCaptureAttempt::find($storedOrder, 1)['state']);

        if ($transactionKey !== null) {
            KlarnaCaptureAttempt::updateUnlessSucceeded($storedOrder, 1, ['transaction_key' => $transactionKey]);
        }

        return wc_get_order($order->get_id());
    }

    private function backdateAttempt(WC_Order $order, int $attemptNumber, int $seconds): void
    {
        $order = wc_get_order($order->get_id());
        $attempts = KlarnaCaptureAttempt::all($order);
        foreach ($attempts as $index => $attempt) {
            if ((int) $attempt['attempt_number'] === $attemptNumber) {
                $attempts[$index]['updated_at'] = gmdate('c', time() - $seconds);
            }
        }
        OrderMeta::update($order, KlarnaCaptureAttempt::META_KEY, $attempts);
    }

    private function lastNote(WC_Order $order): string
    {
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        usort(
            $notes,
            static function ($first, $second) {
                return (int) $second->id <=> (int) $first->id;
            }
        );

        return $notes === [] ? '' : (string) $notes[0]->content;
    }

    private function unscheduleKlarnaActions(): void
    {
        as_unschedule_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::QUEUE_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::CHECK_CAPTURE_HOOK);
    }

    private function createReservedOrder(int $products = 1): WC_Order
    {
        $order = $this->createOrder();
        for ($index = 1; $index <= $products; $index++) {
            $product = new WC_Product_Simple();
            $product->set_name('Status check product ' . $index);
            $product->set_regular_price('25.00');
            $product->set_price('25.00');
            $product->save();
            $this->productIds[] = $product->get_id();
            $order->add_product($product, 1);
        }
        $order->set_payment_method('buckaroo_klarnapay');
        $order->set_currency('EUR');
        $order->calculate_totals();
        $order->update_meta_data('buckaroo_is_reserved', 'yes');
        $order->update_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY, 'DATA-REQUEST-STATUS');
        $order->update_meta_data(KlarnaProcessor::RESERVED_AMOUNT_META_KEY, number_format(25.00 * $products, 2, '.', ''));
        $order->save();

        return wc_get_order($order->get_id());
    }

    private function throwingClient(string $message): BuckarooClient
    {
        $client = $this->getMockBuilder(BuckarooClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['process'])
            ->getMock();
        $client->method('process')->willThrowException(new RuntimeException($message));

        return $client;
    }

    private function statusResponse(WC_Order $order, int $statusCode, string $transactionKey, string $description, ?float $amount = null): TransactionResponse
    {
        return new TransactionResponse(new PsrResponse(200), [
            'Key' => $transactionKey,
            'Invoice' => (string) $order->get_order_number(),
            'ServiceCode' => 'klarna',
            'Status' => [
                'Code' => ['Code' => $statusCode, 'Description' => $description],
                'SubCode' => ['Code' => 'S001', 'Description' => $description],
                'DateTime' => '2026-08-26T12:00:00',
            ],
            'IsTest' => true,
            'Currency' => $order->get_currency(),
            'AmountDebit' => $amount ?? (float) $order->get_total('edit'),
            'TransactionType' => 'C339',
            'Services' => [
                ['Name' => 'klarna', 'Action' => 'Pay', 'Parameters' => []],
            ],
            'RelatedTransactions' => null,
        ]);
    }

    private function successfulResponse(string $transactionKey): TransactionResponse
    {
        $response = $this->getMockBuilder(TransactionResponse::class)
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

    private function createUser(string $role): int
    {
        $userId = wp_create_user(
            uniqid('status-check-', true),
            'password',
            uniqid('status-check-', true) . '@example.com'
        );
        $user = get_user_by('id', $userId);
        $user->set_role($role);
        $this->userIds[] = $userId;

        return $userId;
    }
}
