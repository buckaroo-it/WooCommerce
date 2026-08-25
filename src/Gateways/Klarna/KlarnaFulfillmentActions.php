<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateOrderMetaToHpos;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\KlarnaCaptureAttempt;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use WC_Order;

class KlarnaFulfillmentActions
{
    public const AUTOMATIC_CAPTURE_HOOK = 'buckaroo_klarnapay_automatic_capture';

    public const RECOVER_CAPTURE_HOOK = 'buckaroo_klarnapay_recover_capture';

    public const ACTION_GROUP = 'buckaroo-klarna-capture';

    private ?BuckarooClient $buckarooClient;

    public function __construct(?BuckarooClient $buckarooClient = null)
    {
        $this->buckarooClient = $buckarooClient;
        add_filter('woocommerce_order_actions', [$this, 'add_fulfillment_actions'], 10, 2);

        add_action('woocommerce_order_action_buckaroo_klarnapay_cancel_reservation', [$this, 'handle_cancel_reservation'], 10, 1);
        add_action('woocommerce_order_action_buckaroo_klarnapay_retry_capture', [$this, 'handle_retry_capture'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'handle_completed_order'], 10, 1);
        add_action(self::AUTOMATIC_CAPTURE_HOOK, [$this, 'handle_automatic_capture'], 10, 2);
        add_action(self::RECOVER_CAPTURE_HOOK, [$this, 'handle_recover_capture'], 10, 2);
    }

    public function handle_completed_order($orderId): void
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof WC_Order || $order->get_payment_method() !== 'buckaroo_klarnapay') {
            return;
        }

        $gateway = new KlarnaPayGateway();
        if ($gateway->get_option('automatic_capture', 'no') !== 'yes') {
            return;
        }

        if ($order->get_meta('buckaroo_is_reserved') !== 'yes') {
            return;
        }

        $dataRequestKey = $order->get_meta(KlarnaProcessor::DATA_REQUEST_META_KEY);
        if (! is_string($dataRequestKey) || trim($dataRequestKey) === '') {
            $order->add_order_note(
                __('Automatic Klarna capture was skipped because the Data Request key is missing.', 'wc-buckaroo-bpe-gateway')
            );

            return;
        }

        MigrateOrderMetaToHpos::ensureOrderMigrated($order);
        $order->read_meta_data(true);

        $reservedAmount = $order->get_meta(KlarnaProcessor::RESERVED_AMOUNT_META_KEY);
        if (! is_numeric($reservedAmount)) {
            KlarnaCaptureAttempt::recordSkipped(
                $order,
                CaptureAllocation::forOrder($order),
                __('Automatic Klarna capture was skipped because the reserved amount is unknown.', 'wc-buckaroo-bpe-gateway')
            );

            return;
        }

        $allocation = CaptureAllocation::remainingForOrder(
            $order,
            (float) $reservedAmount
        );
        if ($allocation->getAmount() <= 0) {
            KlarnaCaptureAttempt::recordSkipped(
                $order,
                $allocation,
                __('Automatic Klarna capture was skipped because there is no remaining amount to capture.', 'wc-buckaroo-bpe-gateway')
            );

            return;
        }

        $attempt = KlarnaCaptureAttempt::queue($order, $allocation);
        if ($attempt === null) {
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

    public function handle_automatic_capture($orderId, $attemptNumber): void
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof WC_Order) {
            return;
        }

        $attempt = KlarnaCaptureAttempt::claim($order, (int) $attemptNumber);
        if ($attempt === null) {
            $queuedAttempt = KlarnaCaptureAttempt::find($order, (int) $attemptNumber);
            if (($queuedAttempt['state'] ?? '') === 'queued') {
                $this->rescheduleCapture($order, $queuedAttempt);
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
                    'state' => CaptureResult::SKIPPED,
                    'last_error' => $skipReason,
                ]
            );
            if ($skipped !== null && ($skipped['state'] ?? '') === CaptureResult::SKIPPED) {
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
        $result = (new KlarnaPayGateway())->capture(
            $order,
            $attempt['amount'],
            $allocation,
            $this->buckarooClient,
            (int) $attemptNumber
        );

        $updatedAttempt = KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            (int) $attemptNumber,
            [
                'state' => $result->getStatus(),
                'transaction_key' => $result->getTransactionKey(),
                'last_error' => $result->getStatus() === CaptureResult::SUCCEEDED
                    ? ''
                    : sanitize_text_field($result->getMessage()),
            ]
        );

        if ($updatedAttempt !== null && in_array($updatedAttempt['state'], [CaptureResult::FAILED, CaptureResult::UNKNOWN], true)) {
            KlarnaCaptureAttempt::recordAttention($order, $updatedAttempt);
        } elseif ($updatedAttempt !== null && $updatedAttempt['state'] === CaptureResult::SUCCEEDED) {
            KlarnaCaptureAttempt::clearAttention($order);
        }
    }

    public function handle_recover_capture($orderId, $attemptNumber): void
    {
        $order = wc_get_order($orderId);
        if ($order instanceof WC_Order) {
            KlarnaCaptureAttempt::recoverStale($order, (int) $attemptNumber);
        }
    }

    protected function enqueueCapture(WC_Order $order, array $attempt)
    {
        $pre = apply_filters('buckaroo_klarna_enqueue_capture', null, $order, $attempt);
        if ($pre !== null) {
            return $pre;
        }

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

    private function rescheduleCapture(WC_Order $order, array $attempt): void
    {
        if (! function_exists('as_schedule_single_action')) {
            return;
        }

        $actionId = as_schedule_single_action(
            time() + 5,
            self::AUTOMATIC_CAPTURE_HOOK,
            [$order->get_id(), $attempt['attempt_number']],
            self::ACTION_GROUP,
            false
        );
        if ($actionId) {
            return;
        }

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

        if ((new KlarnaPayGateway())->get_option('automatic_capture', 'no') !== 'yes') {
            return __('Automatic Klarna capture was skipped because automatic capture is disabled.', 'wc-buckaroo-bpe-gateway');
        }

        return '';
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

        if (
            $order->get_payment_method() !== 'buckaroo_klarnapay' ||
            $order->get_meta('buckaroo_is_reserved') !== 'yes'
        ) {
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

        $reservedAmount = $order->get_meta(KlarnaProcessor::RESERVED_AMOUNT_META_KEY);
        $allocation = CaptureAllocation::remainingForOrder(
            $order,
            is_numeric($reservedAmount) ? (float) $reservedAmount : null
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

    /**
     * Handle Cancel Reservation action
     *
     * @param  WC_Order  $order
     * @return void
     */
    public function handle_cancel_reservation(WC_Order $order)
    {
        $gateway = new KlarnaPayGateway();
        $gateway->cancel_reservation($order);

        wp_safe_redirect(admin_url("post.php?post={$order->get_id()}&action=edit"));
        exit;
    }
}
