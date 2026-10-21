<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateOrderMetaToHpos;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use Throwable;
use WC_Order;

class KlarnaFulfillmentActions
{
    public const AUTOMATIC_CAPTURE_HOOK = 'buckaroo_klarnapay_automatic_capture';

    public const RECOVER_CAPTURE_HOOK = 'buckaroo_klarnapay_recover_capture';

    public const QUEUE_CAPTURE_HOOK = 'buckaroo_klarnapay_queue_capture';

    public const CHECK_CAPTURE_HOOK = 'buckaroo_klarnapay_check_capture';

    public const RELEASE_RESERVATION_HOOK = 'buckaroo_klarnapay_release_reservation';

    public const ACTION_GROUP = 'buckaroo-klarna-capture';

    /** Set on a digital-only order that the authorization itself completed. */
    public const CAPTURE_ON_PAYMENT_META_KEY = '_buckaroo_klarna_capture_on_payment';

    private const MAX_QUEUE_ATTEMPTS = 3;

    private const MAX_CAPTURE_RESCHEDULE_ATTEMPTS = 3;

    private const STATUS_CHECK_DELAY = 300;

    private const MAX_STATUS_CHECKS = 3;

    /**
     * Seconds after the push grace period before the first no-key status check
     * runs, so the check never fires a moment before the grace period ends.
     */
    private const GRACE_PERIOD_SLACK = 60;

    private ?BuckarooClient $buckarooClient;

    private ?KlarnaPayGateway $gateway = null;

    public function __construct(?BuckarooClient $buckarooClient = null)
    {
        $this->buckarooClient = $buckarooClient;
        add_filter('woocommerce_order_actions', [$this, 'add_fulfillment_actions'], 10, 2);
        add_filter('buckaroo_push_handled', [KlarnaPushProcessor::class, 'handle'], 10, 3);
        add_filter('buckaroo_push_reservation', [KlarnaPushProcessor::class, 'handleReservation'], 10, 3);
        add_action('admin_notices', [self::class, 'handle_admin_notices']);

        add_action('woocommerce_order_action_buckaroo_klarnapay_cancel_reservation', [$this, 'handle_cancel_reservation'], 10, 1);
        add_action('woocommerce_order_action_buckaroo_klarnapay_retry_capture', [$this, 'handle_retry_capture'], 10, 1);
        add_action('woocommerce_order_action_buckaroo_klarnapay_check_capture', [$this, 'handle_check_capture'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'handle_completed_order'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [$this, 'handle_cancelled_order'], 10, 1);
        add_action(self::QUEUE_CAPTURE_HOOK, [$this, 'handle_completed_order'], 10, 2);
        add_action(self::AUTOMATIC_CAPTURE_HOOK, [$this, 'handle_automatic_capture'], 10, 3);
        add_action(self::RECOVER_CAPTURE_HOOK, [$this, 'handle_recover_capture'], 10, 2);
        add_action(self::CHECK_CAPTURE_HOOK, [$this, 'handle_scheduled_check_capture'], 10, 3);
        add_action(self::RELEASE_RESERVATION_HOOK, [$this, 'handle_release_reservation'], 10, 1);
    }

    /**
     * Once the reserved amount is fully captured the order is paid and fulfilled: mark it
     * paid if it was still on hold, then complete it. Partial captures leave it unchanged,
     * and completing it here does not capture again, since nothing remains to capture.
     */
    public static function completeWhenFullyCaptured(WC_Order $order, string $transactionKey): void
    {
        $captureTarget = KlarnaProcessor::getReservedAmount($order) ?? (float) $order->get_total('edit');
        $capturedAmount = (float) OrderMeta::get($order, '_wc_order_amount_captured');
        if (Helper::roundAmount($capturedAmount) < Helper::roundAmount($captureTarget)) {
            return;
        }

        $order->payment_complete($transactionKey);

        if ($order->has_status(['processing', 'on-hold'])) {
            $order->update_status(
                'completed',
                __('Klarna payment fully captured.', 'wc-buckaroo-bpe-gateway')
            );
        }
    }

    public function handle_completed_order($orderId, $queueAttempt = 0): void
    {
        $order = wc_get_order($orderId);
        if (
            ! $order instanceof WC_Order ||
            $order->get_status() !== 'completed' ||
            $order->get_payment_method() !== 'buckaroo_klarnapay'
        ) {
            return;
        }

        if (! $this->capturesOnCompleted($order)) {
            return;
        }

        if (! $this->hasOpenReservation($order)) {
            return;
        }

        $dataRequestKey = $order->get_meta(KlarnaProcessor::DATA_REQUEST_META_KEY);
        if (! is_string($dataRequestKey) || trim($dataRequestKey) === '') {
            $order->add_order_note(
                __('Automatic Klarna capture was skipped because the Data Request key is missing.', 'wc-buckaroo-bpe-gateway')
            );

            return;
        }

        $reservedAmount = KlarnaProcessor::getReservedAmount($order);
        if ($reservedAmount === null) {
            $order->add_order_note(
                __('Automatic Klarna capture was skipped because the reserved amount is unknown.', 'wc-buckaroo-bpe-gateway')
            );

            return;
        }

        $allocation = CaptureAllocation::remainingForOrder(
            $order,
            (float) $reservedAmount
        );
        if ($allocation->getAmount() <= 0) {
            return;
        }

        $attempt = KlarnaCaptureAttempt::queue($order, $allocation);
        if ($attempt === null) {
            if (! KlarnaCaptureAttempt::hasRelatedAttempt($order, $allocation)) {
                $this->scheduleQueueRetry($order, $allocation, (int) $queueAttempt);
            }

            return;
        }

        if (! $this->enqueueCapture($order, $attempt)) {
            $failed = KlarnaCaptureAttempt::updateUnlessSucceeded(
                $order,
                (int) $attempt['attempt_number'],
                [
                    'state' => CaptureResult::FAILED,
                    'last_error' => __('Automatic Klarna capture could not be scheduled.', 'wc-buckaroo-bpe-gateway'),
                ]
            );
            if ($failed !== null) {
                KlarnaCaptureAttempt::recordAttention($order, $failed);
            }
        }
    }

    /**
     * Queue the release of the open Klarna reservation of a cancelled order, so bulk
     * and automated cancellations do not wait on the Buckaroo API.
     * Keyed on the reservation itself, so orders left On hold by older versions work too.
     */
    public function handle_cancelled_order($orderId): void
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof WC_Order || ! $this->hasOpenReservation($order)) {
            return;
        }

        $args = [$order->get_id()];
        if (
            function_exists('as_enqueue_async_action') &&
            (
                as_enqueue_async_action(self::RELEASE_RESERVATION_HOOK, $args, self::ACTION_GROUP, true) ||
                (function_exists('as_has_scheduled_action') && as_has_scheduled_action(self::RELEASE_RESERVATION_HOOK, $args, self::ACTION_GROUP))
            )
        ) {
            return;
        }

        $this->handle_release_reservation($order->get_id());
    }

    /**
     * Release the open Klarna reservation of a cancelled order that was not captured.
     */
    public function handle_release_reservation($orderId): void
    {
        $order = wc_get_order($orderId);
        if (
            ! $order instanceof WC_Order ||
            $order->get_status() !== 'cancelled' ||
            ! $this->hasOpenReservation($order)
        ) {
            return;
        }

        if (
            KlarnaCaptureAttempt::hasStartedCapture($order) ||
            OrderMeta::get($order, '_wc_order_captures', false) !== []
        ) {
            $order->add_order_note(
                __('The Klarna authorization was not released automatically because a capture was already made or is in progress. Use the "Klarna: Cancel reservation" order action to release what is left.', 'wc-buckaroo-bpe-gateway')
            );

            return;
        }

        try {
            $released = $this->gateway()->release_reservation($order, $this->buckarooClient);
        } catch (Throwable $e) {
            Logger::log(__METHOD__, $e->getMessage());
            $released = false;
        }

        $order->add_order_note(
            $released
                ? __('The Klarna authorization was released because the order was cancelled.', 'wc-buckaroo-bpe-gateway')
                : __('The Klarna authorization could not be released automatically. It may already have been released or expired; otherwise use the "Klarna: Cancel reservation" order action to release it.', 'wc-buckaroo-bpe-gateway')
        );
    }

    private function hasOpenReservation(WC_Order $order): bool
    {
        if ($order->get_payment_method() !== 'buckaroo_klarnapay') {
            return false;
        }

        MigrateOrderMetaToHpos::ensureOrderMigrated($order);
        $order->read_meta_data(true);

        return $order->get_meta('buckaroo_is_reserved') === 'yes';
    }

    public function handle_automatic_capture($orderId, $attemptNumber, $rescheduleAttempt = 0): void
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof WC_Order) {
            return;
        }

        $attempt = KlarnaCaptureAttempt::claim($order, (int) $attemptNumber);
        if ($attempt === null) {
            $queuedAttempt = KlarnaCaptureAttempt::find($order, (int) $attemptNumber);
            if (($queuedAttempt['state'] ?? '') === 'queued') {
                $this->rescheduleCapture($order, $queuedAttempt, (int) $rescheduleAttempt);
            }

            return;
        }

        if (! $this->scheduleRecovery($order, $attempt)) {
            $failed = KlarnaCaptureAttempt::updateUnlessSucceeded(
                $order,
                (int) $attemptNumber,
                [
                    'state' => CaptureResult::FAILED,
                    'last_error' => __('Automatic Klarna capture recovery could not be scheduled.', 'wc-buckaroo-bpe-gateway'),
                ]
            );
            if ($failed !== null) {
                KlarnaCaptureAttempt::recordAttention($order, $failed);
            }

            return;
        }

        $skipReason = $this->getIneligibleReason($order);
        if ($skipReason !== '') {
            $skipped = KlarnaCaptureAttempt::updateUnlessSucceeded(
                $order,
                (int) $attemptNumber,
                [
                    'state' => KlarnaCaptureAttempt::SKIPPED,
                    'last_error' => $skipReason,
                ]
            );
            if ($skipped !== null && ($skipped['state'] ?? '') === KlarnaCaptureAttempt::SKIPPED) {
                $order->add_order_note($skipReason);
            }

            return;
        }

        $storedAllocation = $attempt['allocation'];
        $allocation = CaptureAllocation::fromArrays(
            $storedAllocation['line_item_qtys'],
            $storedAllocation['line_item_totals'],
            $storedAllocation['line_item_tax_totals']
        );
        $this->gateway()->capture(
            $order,
            $attempt['amount'],
            $allocation,
            $this->buckarooClient,
            (int) $attemptNumber
        );
    }

    public function handle_recover_capture($orderId, $attemptNumber): void
    {
        $order = wc_get_order($orderId);
        if ($order instanceof WC_Order) {
            $recovered = KlarnaCaptureAttempt::recoverStale($order, (int) $attemptNumber);
            if ($recovered !== null) {
                self::scheduleStatusCheck($order, $recovered);

                return;
            }

            $failed = KlarnaCaptureAttempt::failQueued(
                $order,
                (int) $attemptNumber,
                __('Automatic Klarna capture stopped after repeated worker lock contention.', 'wc-buckaroo-bpe-gateway')
            );
            if ($failed !== null) {
                KlarnaCaptureAttempt::recordAttention($order, $failed);
            }
        }
    }

    public function handle_check_capture(WC_Order $order): void
    {
        if (! current_user_can('edit_shop_orders')) {
            return;
        }

        (new KlarnaCaptureStatusCheck($this->buckarooClient))->run($order);
    }

    public function handle_scheduled_check_capture($orderId, $attemptNumber = 0, $checkAttempt = 0): void
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof WC_Order || (int) $attemptNumber <= 0) {
            return;
        }

        $attempt = (new KlarnaCaptureStatusCheck($this->buckarooClient))->run($order, (int) $attemptNumber);
        if ($attempt !== null && KlarnaCaptureAttempt::isCheckable($attempt)) {
            self::scheduleStatusCheck($order, $attempt, (int) $checkAttempt + 1);
        }
    }

    /**
     * Schedule a bounded status check for attempts whose outcome is unknown or
     * pending. Without an explicit attempt, every unknown attempt is scheduled.
     *
     * The first check of an attempt without a transaction key waits for the push
     * grace period; every other check runs shortly after.
     */
    public static function scheduleStatusCheck(WC_Order $order, ?array $attempt = null, int $checkAttempt = 0): bool
    {
        if ($attempt === null) {
            $scheduled = false;
            foreach (KlarnaCaptureAttempt::checkable($order) as $candidate) {
                if (($candidate['state'] ?? '') === CaptureResult::UNKNOWN) {
                    $scheduled = self::scheduleStatusCheck($order, $candidate, $checkAttempt) || $scheduled;
                }
            }

            return $scheduled;
        }

        if (
            ! KlarnaCaptureAttempt::isCheckable($attempt) ||
            $checkAttempt >= self::MAX_STATUS_CHECKS ||
            ! function_exists('as_schedule_single_action')
        ) {
            return false;
        }

        $delay = $checkAttempt === 0 && trim((string) ($attempt['transaction_key'] ?? '')) === ''
            ? KlarnaCaptureStatusCheck::NO_KEY_GRACE_PERIOD + self::GRACE_PERIOD_SLACK
            : self::STATUS_CHECK_DELAY;
        $args = [$order->get_id(), (int) $attempt['attempt_number'], $checkAttempt];
        $actionId = as_schedule_single_action(
            time() + $delay,
            self::CHECK_CAPTURE_HOOK,
            $args,
            self::ACTION_GROUP,
            true
        );
        if (
            $actionId ||
            (function_exists('as_has_scheduled_action') && as_has_scheduled_action(self::CHECK_CAPTURE_HOOK, $args, self::ACTION_GROUP))
        ) {
            return true;
        }

        $order->add_order_note(
            sprintf(
                __('Automatic Klarna capture status check for attempt %d could not be scheduled. Use the "Klarna: Check capture status" order action.', 'wc-buckaroo-bpe-gateway'),
                (int) $attempt['attempt_number']
            )
        );

        return false;
    }

    protected function enqueueCapture(WC_Order $order, array $attempt)
    {
        if (! function_exists('as_enqueue_async_action')) {
            return false;
        }

        $actionId = as_enqueue_async_action(
            self::AUTOMATIC_CAPTURE_HOOK,
            [$order->get_id(), $attempt['attempt_number']],
            self::ACTION_GROUP,
            true
        );
        $actionArgs = [$order->get_id(), $attempt['attempt_number']];
        if (
            ! $actionId &&
            (! function_exists('as_has_scheduled_action') || ! as_has_scheduled_action(
                self::AUTOMATIC_CAPTURE_HOOK,
                $actionArgs,
                self::ACTION_GROUP
            ))
        ) {
            return false;
        }

        if (! function_exists('as_schedule_single_action')) {
            return false;
        }

        $recoveryId = as_schedule_single_action(
            time() + KlarnaCaptureAttempt::STALE_AFTER,
            self::RECOVER_CAPTURE_HOOK,
            [$order->get_id(), $attempt['attempt_number']],
            self::ACTION_GROUP,
            true
        );
        if (
            ! $recoveryId &&
            (! function_exists('as_has_scheduled_action') || ! as_has_scheduled_action(
                self::RECOVER_CAPTURE_HOOK,
                $actionArgs,
                self::ACTION_GROUP
            ))
        ) {
            if (function_exists('as_unschedule_action')) {
                as_unschedule_action(
                    self::AUTOMATIC_CAPTURE_HOOK,
                    [$order->get_id(), $attempt['attempt_number']],
                    self::ACTION_GROUP
                );
            }

            return false;
        }

        return $actionId;
    }

    private function scheduleRecovery(WC_Order $order, array $attempt): bool
    {
        if (! function_exists('as_schedule_single_action')) {
            return false;
        }

        $args = [$order->get_id(), $attempt['attempt_number']];
        if (function_exists('as_unschedule_action')) {
            as_unschedule_action(self::RECOVER_CAPTURE_HOOK, $args, self::ACTION_GROUP);
        }

        return (bool) as_schedule_single_action(
            time() + KlarnaCaptureAttempt::STALE_AFTER,
            self::RECOVER_CAPTURE_HOOK,
            $args,
            self::ACTION_GROUP,
            true
        );
    }

    private function scheduleQueueRetry(
        WC_Order $order,
        CaptureAllocation $allocation,
        int $queueAttempt
    ): void {
        $nextAttempt = $queueAttempt + 1;
        if ($nextAttempt > self::MAX_QUEUE_ATTEMPTS || ! function_exists('as_schedule_single_action')) {
            KlarnaCaptureAttempt::recordAttention(
                $order,
                [
                    'attempt_number' => 0,
                    'state' => CaptureResult::UNKNOWN,
                    'amount' => $allocation->getAmount(),
                    'currency' => $order->get_currency(),
                    'last_error' => __('Automatic Klarna capture could not create a durable attempt.', 'wc-buckaroo-bpe-gateway'),
                ]
            );

            return;
        }

        $args = [$order->get_id(), $nextAttempt];
        $actionId = as_schedule_single_action(
            time() + 5,
            self::QUEUE_CAPTURE_HOOK,
            $args,
            self::ACTION_GROUP,
            true
        );
        if (
            ! $actionId &&
            (! function_exists('as_has_scheduled_action') || ! as_has_scheduled_action(
                self::QUEUE_CAPTURE_HOOK,
                $args,
                self::ACTION_GROUP
            ))
        ) {
            $order->add_order_note(
                __('Automatic Klarna capture could not retry durable attempt creation.', 'wc-buckaroo-bpe-gateway')
            );
        }
    }

    private function rescheduleCapture(WC_Order $order, array $attempt, int $rescheduleAttempt): void
    {
        $nextAttempt = $rescheduleAttempt + 1;
        if ($nextAttempt > self::MAX_CAPTURE_RESCHEDULE_ATTEMPTS) {
            if ($this->scheduleRecovery($order, $attempt)) {
                KlarnaCaptureAttempt::recordAttention(
                    $order,
                    array_merge($attempt, [
                        'state' => CaptureResult::UNKNOWN,
                        'last_error' => __('Automatic Klarna capture is waiting for final lock recovery.', 'wc-buckaroo-bpe-gateway'),
                    ])
                );
            } else {
                $this->failReschedule($order, $attempt);
            }

            return;
        }
        if (! function_exists('as_schedule_single_action')) {
            return;
        }

        $actionId = as_schedule_single_action(
            time() + 5,
            self::AUTOMATIC_CAPTURE_HOOK,
            [$order->get_id(), $attempt['attempt_number'], $nextAttempt],
            self::ACTION_GROUP,
            false
        );
        if ($actionId) {
            return;
        }

        $this->failReschedule($order, $attempt);
    }

    private function failReschedule(WC_Order $order, array $attempt): void
    {
        $failed = KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            (int) $attempt['attempt_number'],
            [
                'state' => CaptureResult::FAILED,
                'last_error' => __('Automatic Klarna capture could not be rescheduled after lock contention.', 'wc-buckaroo-bpe-gateway'),
            ]
        );
        if ($failed !== null) {
            KlarnaCaptureAttempt::recordAttention($order, $failed);
        } else {
            KlarnaCaptureAttempt::recordAttention(
                $order,
                array_merge($attempt, [
                    'state' => CaptureResult::UNKNOWN,
                    'last_error' => __('Automatic Klarna capture could not recover from lock contention.', 'wc-buckaroo-bpe-gateway'),
                ])
            );
        }
    }

    private function getIneligibleReason(WC_Order $order): string
    {
        if ($order->get_status() !== 'completed') {
            return __('Automatic Klarna capture was skipped because the order is no longer Completed.', 'wc-buckaroo-bpe-gateway');
        }
        if ($order->get_payment_method() !== 'buckaroo_klarnapay') {
            return __('Automatic Klarna capture was skipped because the payment method changed.', 'wc-buckaroo-bpe-gateway');
        }
        if ($order->get_meta('buckaroo_is_reserved') !== 'yes') {
            return __('Automatic Klarna capture was skipped because the reservation is no longer active.', 'wc-buckaroo-bpe-gateway');
        }

        $dataRequestKey = $order->get_meta(KlarnaProcessor::DATA_REQUEST_META_KEY);
        if (! is_string($dataRequestKey) || trim($dataRequestKey) === '') {
            return __('Automatic Klarna capture was skipped because the Data Request key is missing.', 'wc-buckaroo-bpe-gateway');
        }

        if (! $this->capturesOnCompleted($order)) {
            return __('Automatic Klarna capture was skipped because automatic capture is disabled.', 'wc-buckaroo-bpe-gateway');
        }

        return '';
    }

    /**
     * Completed captures when automatic capture is on, or when the payment itself
     * completed a digital-only order (see KlarnaPushProcessor::handleReservation()).
     */
    private function capturesOnCompleted(WC_Order $order): bool
    {
        return KlarnaPayGateway::isAutomaticCaptureEnabled() ||
            $order->get_meta(self::CAPTURE_ON_PAYMENT_META_KEY) === 'yes';
    }

    /**
     * Add Klarna fulfillment actions to the WooCommerce order actions dropdown
     *
     * @param  array  $actions
     * @param  WC_Order|null  $order
     * @return array
     */
    public function add_fulfillment_actions($actions, $order = null)
    {
        global $theorder;

        if ($order === null) {
            if (! ($theorder instanceof WC_Order)) {
                return $actions;
            }
            $order = $theorder;
        }

        if ($order->get_payment_method() !== 'buckaroo_klarnapay') {
            return $actions;
        }

        // An unconfirmed capture stays checkable after the rest of the reservation was released.
        if (current_user_can('edit_shop_orders') && KlarnaCaptureAttempt::canCheckStatus($order)) {
            $actions['buckaroo_klarnapay_check_capture'] = esc_html__('Klarna: Check capture status', 'wc-buckaroo-bpe-gateway');
        }

        if ($order->get_meta('buckaroo_is_reserved') !== 'yes') {
            return $actions;
        }

        $actions['buckaroo_klarnapay_cancel_reservation'] = esc_html__('Klarna: Cancel reservation', 'wc-buckaroo-bpe-gateway');

        if (current_user_can('edit_shop_orders') && KlarnaCaptureAttempt::canRetry($order)) {
            $actions['buckaroo_klarnapay_retry_capture'] = esc_html__('Klarna: Retry capture', 'wc-buckaroo-bpe-gateway');
        }

        return $actions;
    }

    public function handle_retry_capture(WC_Order $order): void
    {
        if (! current_user_can('edit_shop_orders') || ! KlarnaCaptureAttempt::canRetry($order)) {
            return;
        }

        $allocation = CaptureAllocation::remainingForOrder(
            $order,
            KlarnaProcessor::getReservedAmount($order)
        );
        $attempt = KlarnaCaptureAttempt::retry($order, $allocation);
        if ($attempt === null) {
            return;
        }

        $order->add_order_note(
            sprintf(
                __('Klarna capture retry queued (attempt %1$d) for %2$s %3$s.', 'wc-buckaroo-bpe-gateway'),
                $attempt['attempt_number'],
                $attempt['amount'],
                $attempt['currency']
            )
        );

        if (! $this->enqueueCapture($order, $attempt)) {
            $failed = KlarnaCaptureAttempt::updateUnlessSucceeded(
                $order,
                (int) $attempt['attempt_number'],
                [
                    'state' => CaptureResult::FAILED,
                    'last_error' => __('Klarna capture retry could not be scheduled.', 'wc-buckaroo-bpe-gateway'),
                ]
            );
            if ($failed !== null) {
                KlarnaCaptureAttempt::recordAttention($order, $failed);
            }
        }
    }

    public static function handle_admin_notices(): void
    {
        if (! current_user_can('edit_shop_orders')) {
            return;
        }

        foreach (KlarnaCaptureAttempt::notifications() as $notification) {
            $order = wc_get_order($notification['order_id']);
            if (! $order) {
                continue;
            }

            $type = $notification['state'] === 'failed' ? 'error' : 'warning';
            $message = $notification['state'] === 'failed'
                ? __('Klarna capture failed', 'wc-buckaroo-bpe-gateway')
                : __('Klarna capture outcome is unknown', 'wc-buckaroo-bpe-gateway');
            printf(
                '<div class="notice notice-%1$s"><p>%2$s: %3$s. <a href="%4$s">%5$s #%6$d</a></p></div>',
                esc_attr($type),
                esc_html($message),
                esc_html($notification['error']),
                esc_url($order->get_edit_order_url()),
                esc_html__('Review order', 'wc-buckaroo-bpe-gateway'),
                (int) $order->get_id()
            );
        }
    }

    /**
     * Handle Cancel Reservation action
     *
     * @param  WC_Order  $order
     * @return void
     */
    public function handle_cancel_reservation(WC_Order $order)
    {
        $gateway = $this->gateway();
        $gateway->cancel_reservation($order);

        wp_safe_redirect(admin_url("post.php?post={$order->get_id()}&action=edit"));
        exit;
    }

    private function gateway(): KlarnaPayGateway
    {
        if ($this->gateway === null) {
            $this->gateway = new KlarnaPayGateway();
        } else {
            $this->gateway->init_settings();
        }

        return $this->gateway;
    }
}
