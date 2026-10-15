<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaCaptureAttempt;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaFulfillmentActions;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\ResponseParser\FormDataParser;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

/**
 * An authorized Klarna (MoR) order goes to Processing (digital only: Completed and
 * captured), and cancelling an uncaptured order releases the authorization.
 */
class Test_KlarnaAuthorizedOrderStatus extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    /** @var KlarnaFulfillmentActions|null */
    private $actions;

    /** @var int[] */
    private $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        $this->enableHpos();
        delete_option('woocommerce_buckaroo_klarnapay_settings');
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        $this->unscheduleCaptureActions();
    }

    protected function tearDown(): void
    {
        $this->unscheduleCaptureActions();
        if ($this->actions !== null) {
            remove_action('woocommerce_order_status_completed', [$this->actions, 'handle_completed_order'], 10);
            remove_action('woocommerce_order_status_cancelled', [$this->actions, 'handle_cancelled_order'], 10);
            remove_action(KlarnaFulfillmentActions::RELEASE_RESERVATION_HOOK, [$this->actions, 'handle_release_reservation'], 10);
            $this->actions = null;
        }

        wp_set_current_user(0);
        foreach ($this->userIds as $userId) {
            wp_delete_user($userId);
        }

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

    public function test_an_authorized_order_goes_to_processing_with_a_note_to_complete_it(): void
    {
        $this->actions = new KlarnaFulfillmentActions();
        $order = $this->createKlarnaOrder(false);

        $this->reservationPush($order);

        $stored = wc_get_order($order->get_id());
        $this->assertSame('processing', $stored->get_status());
        $this->assertSame('yes', $stored->get_meta('buckaroo_is_reserved'));
        $this->assertSame([], KlarnaCaptureAttempt::all($stored), 'Nothing is captured before Completed');
        $this->assertNotNull($this->findNote($stored, 'Set the order to Completed after shipping to collect the payment.'));
    }

    public function test_the_note_says_to_capture_manually_when_automatic_capture_is_off(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'no']);
        $this->actions = new KlarnaFulfillmentActions();
        $order = $this->createKlarnaOrder(false);

        $this->reservationPush($order);

        $stored = wc_get_order($order->get_id());
        $this->assertSame('processing', $stored->get_status());
        $this->assertNotNull($this->findNote($stored, 'Automatic capture is off'));
    }

    public function test_a_digital_only_order_is_completed_and_captured_even_when_automatic_capture_is_off(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'no']);
        $this->actions = new KlarnaFulfillmentActions();
        $order = $this->createKlarnaOrder(true);

        $this->reservationPush($order);

        $stored = wc_get_order($order->get_id());
        $this->assertSame('completed', $stored->get_status());
        $this->assertSame('yes', $stored->get_meta(KlarnaFulfillmentActions::CAPTURE_ON_PAYMENT_META_KEY));
        $attempts = KlarnaCaptureAttempt::all($stored);
        $this->assertCount(1, $attempts);
        $this->assertSame('queued', $attempts[array_key_first($attempts)]['state']);
        $this->assertTrue(as_has_scheduled_action(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK));
        $this->assertNotNull($this->findNote($stored, 'contains only digital products'));
    }

    public function test_the_digital_only_note_asks_for_a_manual_capture_when_none_was_queued(): void
    {
        $this->actions = new KlarnaFulfillmentActions();
        $order = $this->createKlarnaOrder(true);

        // Simulate a capture that could not be queued: nothing handles Completed.
        $completedHooks = $GLOBALS['wp_filter']['woocommerce_order_status_completed'] ?? null;
        unset($GLOBALS['wp_filter']['woocommerce_order_status_completed']);
        try {
            $this->reservationPush($order);
        } finally {
            if ($completedHooks !== null) {
                $GLOBALS['wp_filter']['woocommerce_order_status_completed'] = $completedHooks;
            }
        }

        $stored = wc_get_order($order->get_id());
        $this->assertSame('completed', $stored->get_status());
        $this->assertNull($this->findNote($stored, 'the payment is being collected now'));
        $this->assertNotNull($this->findNote($stored, 'could not be collected automatically'));
    }

    public function test_an_unconfirmed_capture_stays_checkable_after_the_reservation_was_released(): void
    {
        wp_set_current_user($this->createShopManager());
        $this->actions = new KlarnaFulfillmentActions();
        $order = $this->createReservedOrder('processing');
        $attempt = KlarnaCaptureAttempt::queue($order, CaptureAllocation::forOrder($order));
        KlarnaCaptureAttempt::updateUnlessSucceeded($order, (int) $attempt['attempt_number'], ['state' => 'unknown']);
        $order = wc_get_order($order->get_id());
        $order->delete_meta_data('buckaroo_is_reserved');
        $order->save();

        $actions = $this->actions->add_fulfillment_actions([], wc_get_order($order->get_id()));

        $this->assertArrayHasKey('buckaroo_klarnapay_check_capture', $actions);
        $this->assertArrayNotHasKey('buckaroo_klarnapay_cancel_reservation', $actions);
    }

    public function test_cancelling_queues_the_release_instead_of_calling_buckaroo_in_the_status_change(): void
    {
        $client = new CancelReservationBuckarooClient($this->response(true));
        $this->actions = new KlarnaFulfillmentActions($client);
        $order = $this->createReservedOrder('processing');

        $order->update_status('cancelled');

        $this->assertSame(0, $client->cancelCount);
        $this->assertTrue(as_has_scheduled_action(
            KlarnaFulfillmentActions::RELEASE_RESERVATION_HOOK,
            [$order->get_id()],
            KlarnaFulfillmentActions::ACTION_GROUP
        ));
    }

    public function test_a_queued_release_is_skipped_when_the_order_was_reopened(): void
    {
        $client = new CancelReservationBuckarooClient($this->response(true));
        $this->actions = new KlarnaFulfillmentActions($client);
        $order = $this->createReservedOrder('processing');

        $order->update_status('cancelled');
        wc_get_order($order->get_id())->update_status('processing');
        $this->actions->handle_release_reservation($order->get_id());

        $this->assertSame(0, $client->cancelCount);
        $this->assertSame('yes', wc_get_order($order->get_id())->get_meta('buckaroo_is_reserved'));
    }

    public function test_cancelling_an_uncaptured_order_releases_the_authorization(): void
    {
        $client = new CancelReservationBuckarooClient($this->response(true));
        $this->actions = new KlarnaFulfillmentActions($client);
        $order = $this->createReservedOrder('processing');

        $this->cancel($order);

        $stored = wc_get_order($order->get_id());
        $this->assertSame(1, $client->cancelCount);
        $this->assertSame('DATA-REQUEST-KEY', $client->payload['dataRequestKey']);
        $this->assertSame('', $stored->get_meta('buckaroo_is_reserved'));
        $this->assertSame('cancelled', $stored->get_status());
        $this->assertNotNull($this->findNote($stored, 'released because the order was cancelled'));
    }

    public function test_an_order_that_was_on_hold_before_the_update_is_released_on_cancel(): void
    {
        $client = new CancelReservationBuckarooClient($this->response(true));
        $this->actions = new KlarnaFulfillmentActions($client);
        $order = $this->createReservedOrder('on-hold');

        $this->cancel($order);

        $this->assertSame(1, $client->cancelCount);
        $this->assertSame('', wc_get_order($order->get_id())->get_meta('buckaroo_is_reserved'));
    }

    public function test_an_order_that_was_on_hold_before_the_update_is_captured_on_completed(): void
    {
        $this->actions = new KlarnaFulfillmentActions();
        $order = $this->createReservedOrder('on-hold');

        $order->update_status('completed');

        $this->assertCount(1, KlarnaCaptureAttempt::all(wc_get_order($order->get_id())));
    }

    public function test_a_failed_release_keeps_the_reservation_open_and_adds_a_note(): void
    {
        $client = new CancelReservationBuckarooClient($this->response(false));
        $this->actions = new KlarnaFulfillmentActions($client);
        $order = $this->createReservedOrder('processing');

        $this->cancel($order);

        $stored = wc_get_order($order->get_id());
        $this->assertSame('yes', $stored->get_meta('buckaroo_is_reserved'));
        $this->assertNotNull($this->findNote($stored, 'It may already have been released or expired'));
    }

    public function test_a_captured_order_is_not_released_on_cancel(): void
    {
        $client = new CancelReservationBuckarooClient($this->response(true));
        $this->actions = new KlarnaFulfillmentActions($client);
        $order = $this->createReservedOrder('processing');
        KlarnaCaptureAttempt::queue($order, CaptureAllocation::forOrder($order));

        $this->cancel($order);

        $stored = wc_get_order($order->get_id());
        $this->assertSame(0, $client->cancelCount);
        $this->assertSame('yes', $stored->get_meta('buckaroo_is_reserved'));
        $this->assertNotNull($this->findNote($stored, 'was not released automatically'));
    }

    public function test_a_released_reservation_is_not_released_again(): void
    {
        $client = new CancelReservationBuckarooClient($this->response(true));
        $this->actions = new KlarnaFulfillmentActions($client);
        $order = $this->createReservedOrder('processing');

        $this->cancel($order);
        $order = wc_get_order($order->get_id());
        $order->update_status('pending');
        $this->cancel($order);

        $this->assertSame(1, $client->cancelCount);
    }

    public function test_automatic_capture_stays_off_for_settings_saved_without_the_option(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['enabled' => 'yes']);

        $this->assertFalse(KlarnaPayGateway::isAutomaticCaptureEnabled());
        $this->assertSame('no', (new KlarnaPayGateway())->get_option('automatic_capture'));
        $this->assertSame(['enabled' => 'yes'], get_option('woocommerce_buckaroo_klarnapay_settings'), 'Stored settings are not changed');
    }

    public function test_a_saved_automatic_capture_choice_is_kept(): void
    {
        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'no']);
        $this->assertFalse(KlarnaPayGateway::isAutomaticCaptureEnabled());

        update_option('woocommerce_buckaroo_klarnapay_settings', ['automatic_capture' => 'yes']);
        $this->assertTrue(KlarnaPayGateway::isAutomaticCaptureEnabled());
    }

    public function test_automatic_capture_is_on_for_a_new_install(): void
    {
        $this->assertTrue(KlarnaPayGateway::isAutomaticCaptureEnabled());
        $this->assertSame('yes', (new KlarnaPayGateway())->get_option('automatic_capture'));
    }

    public function test_a_confirmed_cancel_reservation_push_closes_the_reservation(): void
    {
        $client = new CancelReservationBuckarooClient($this->response(true));
        $this->actions = new KlarnaFulfillmentActions($client);
        $order = $this->createReservedOrder('processing');
        $push = new FormDataParser([
            'brq_action' => 'CancelReservation',
            'brq_statuscode' => '190',
            'brq_transaction_method' => 'klarna',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);

        $this->assertTrue(apply_filters('buckaroo_push_handled', false, $order, $push));
        $this->cancel(wc_get_order($order->get_id()));

        $this->assertSame('', wc_get_order($order->get_id())->get_meta('buckaroo_is_reserved'));
        $this->assertSame(0, $client->cancelCount, 'An already cancelled reservation is not released again');
    }

    public function test_a_replayed_push_on_an_older_reserved_order_does_not_authorize_it_again(): void
    {
        $this->actions = new KlarnaFulfillmentActions();
        $order = $this->createReservedOrder('completed');
        $push = new FormDataParser([
            'brq_action' => 'Reserve',
            'brq_statuscode' => '190',
            'brq_transactions' => 'RESERVE-PUSH',
            'brq_transaction_method' => 'klarna',
            'brq_datarequest' => 'DATA-REQUEST-KEY',
        ]);

        $context = apply_filters('buckaroo_push_reservation', null, $order, $push);

        $this->assertFalse($context['completed_order']);
        $this->assertNull($this->findNote(wc_get_order($order->get_id()), 'Klarna payment authorized'));
    }

    private function reservationPush(WC_Order $order): void
    {
        if (function_exists('wc_load_cart') && WC()->cart === null) {
            wc_load_cart();
        }

        $responseParser = new FormDataParser([
            'brq_action' => 'Reserve',
            'brq_statuscode' => '190',
            'brq_amount' => '25.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'RESERVE-PUSH',
            'brq_transaction_method' => 'klarna',
            'brq_datarequest' => 'DATA-REQUEST-KEY',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);
        $responseParser->set('coreStatus', BuckarooTransactionStatus::fromTransactionStatus($responseParser->getStatusCode()));

        $reflectionMethod = new \ReflectionMethod(PushProcessor::class, 'onSuccess');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke(new PushProcessor(), $order->get_id(), $order, $responseParser);
    }

    private function createKlarnaOrder(bool $digital): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name($digital ? 'Digital product' : 'Physical product');
        $product->set_regular_price('25.00');
        $product->set_price('25.00');
        $product->set_virtual($digital);
        $product->set_downloadable($digital);
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

    private function createReservedOrder(string $status): WC_Order
    {
        $order = $this->createKlarnaOrder(false);
        $order->update_meta_data('buckaroo_is_reserved', 'yes');
        $order->update_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY, 'DATA-REQUEST-KEY');
        $order->update_meta_data(KlarnaProcessor::RESERVED_AMOUNT_META_KEY, '25.00');
        $order->set_status($status);
        $order->save();

        return wc_get_order($order->get_id());
    }

    private function findNote(WC_Order $order, string $needle): ?string
    {
        foreach (wc_get_order_notes(['order_id' => $order->get_id()]) as $note) {
            if (strpos($note->content, $needle) !== false) {
                return $note->content;
            }
        }

        return null;
    }

    private function response(bool $success): TransactionResponse
    {
        $response = $this->getMockBuilder(TransactionResponse::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSuccess'])
            ->getMock();
        $response->method('isSuccess')->willReturn($success);

        return $response;
    }

    private function unscheduleCaptureActions(): void
    {
        as_unschedule_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::RECOVER_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::CHECK_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::QUEUE_CAPTURE_HOOK);
        as_unschedule_all_actions(KlarnaFulfillmentActions::RELEASE_RESERVATION_HOOK);
        remove_all_actions(KlarnaFulfillmentActions::AUTOMATIC_CAPTURE_HOOK);
    }

    private function createShopManager(): int
    {
        $userId = wp_insert_user([
            'user_login' => 'klarna_manager_' . wp_generate_password(8, false),
            'user_pass' => wp_generate_password(),
            'role' => 'shop_manager',
        ]);
        $this->userIds[] = $userId;

        return $userId;
    }

    /**
     * Cancel the order and run the release job it queued, as Action Scheduler would.
     */
    private function cancel(WC_Order $order): void
    {
        $order->update_status('cancelled');

        $args = [$order->get_id()];
        if (as_has_scheduled_action(KlarnaFulfillmentActions::RELEASE_RESERVATION_HOOK, $args, KlarnaFulfillmentActions::ACTION_GROUP)) {
            as_unschedule_action(KlarnaFulfillmentActions::RELEASE_RESERVATION_HOOK, $args, KlarnaFulfillmentActions::ACTION_GROUP);
            $this->actions->handle_release_reservation($order->get_id());
        }
    }
}

class CancelReservationBuckarooClient extends BuckarooClient
{
    /** @var int */
    public $cancelCount = 0;

    /** @var array<string, mixed> */
    public $payload = [];

    /** @var TransactionResponse */
    private $response;

    public function __construct(TransactionResponse $response)
    {
        $this->response = $response;
    }

    public function method(string $serviceCode)
    {
        $client = $this;

        return new class($client) {
            private $client;

            public function __construct($client)
            {
                $this->client = $client;
            }

            public function cancelReserve(array $payload): TransactionResponse
            {
                return $this->client->recordCancel($payload);
            }
        };
    }

    public function recordCancel(array $payload): TransactionResponse
    {
        $this->cancelCount++;
        $this->payload = $payload;

        return $this->response;
    }
}
