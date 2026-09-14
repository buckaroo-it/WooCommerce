<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Throwable;
use WC_Order;

/**
 * Resolves capture attempts whose outcome is unknown or pending by asking
 * Buckaroo for the transaction status instead of waiting for a push.
 *
 * Never sends a Pay request.
 */
class KlarnaCaptureStatusCheck
{
    /**
     * Seconds an attempt without a Buckaroo transaction key waits for a push
     * before it is marked as failed so it can be retried.
     */
    public const NO_KEY_GRACE_PERIOD = 3600;

    private ?BuckarooClient $buckarooClient;

    public function __construct(?BuckarooClient $buckarooClient = null)
    {
        $this->buckarooClient = $buckarooClient;
    }

    /**
     * Check one attempt, or every unknown/pending attempt when no number is given.
     *
     * @return array|null The last checked attempt after the check, or null when there was nothing to check.
     */
    public function run(WC_Order $order, ?int $attemptNumber = null): ?array
    {
        $result = null;
        foreach (KlarnaCaptureAttempt::checkable($order) as $attempt) {
            if ($attemptNumber !== null && (int) $attempt['attempt_number'] !== $attemptNumber) {
                continue;
            }

            $transactionKey = trim((string) ($attempt['transaction_key'] ?? ''));
            $result = $transactionKey === ''
                ? $this->resolveWithoutTransactionKey($order, $attempt)
                : $this->resolveWithTransactionKey($order, $attempt, $transactionKey);
        }

        return $result;
    }

    private function resolveWithTransactionKey(WC_Order $order, array $attempt, string $transactionKey): array
    {
        $attemptNumber = (int) $attempt['attempt_number'];

        try {
            $response = $this->buckarooClient()->transactionStatus($transactionKey);
            $handled = KlarnaPushProcessor::reconcileCapture(
                $order,
                ResponseRegistry::getResponse($response->toArray())
            );
        } catch (Throwable $exception) {
            $order->add_order_note(
                sprintf(
                    __('Klarna capture status check for attempt %1$d could not be completed: %2$s', 'wc-buckaroo-bpe-gateway'),
                    $attemptNumber,
                    sanitize_text_field($exception->getMessage())
                )
            );

            return $this->recoverAfterFailure($order, $attemptNumber, $exception) ?? $attempt;
        }

        $updated = KlarnaCaptureAttempt::find($order, $attemptNumber) ?? $attempt;
        if (! $handled) {
            $order->add_order_note(
                sprintf(
                    __('Klarna capture status check: Buckaroo transaction %1$s could not be matched to capture attempt %2$d.', 'wc-buckaroo-bpe-gateway'),
                    $transactionKey,
                    $attemptNumber
                )
            );

            return $updated;
        }

        $order->add_order_note(
            sprintf(
                __('Klarna capture status check: attempt %1$d is now %2$s (Buckaroo transaction %3$s).', 'wc-buckaroo-bpe-gateway'),
                $attemptNumber,
                $updated['state'],
                $transactionKey
            )
        );

        return $updated;
    }

    /**
     * Reconciliation may throw after it moved the attempt forward. The push
     * path relies on Buckaroo replaying the push; here nobody replays, so put
     * the attempt back into a state the next check can resolve.
     */
    private function recoverAfterFailure(WC_Order $order, int $attemptNumber, Throwable $exception): ?array
    {
        $attempt = KlarnaCaptureAttempt::find($order, $attemptNumber);
        if ($attempt === null) {
            return null;
        }

        if (($attempt['state'] ?? '') === CaptureResult::SUCCEEDED) {
            KlarnaCaptureAttempt::clearAttention($order);

            return $attempt;
        }

        if (($attempt['state'] ?? '') === KlarnaCaptureAttempt::IN_PROGRESS) {
            return KlarnaCaptureAttempt::updateUnlessSucceeded(
                $order,
                $attemptNumber,
                [
                    'state' => CaptureResult::UNKNOWN,
                    'last_error' => sanitize_text_field($exception->getMessage()),
                ]
            ) ?? $attempt;
        }

        return $attempt;
    }

    private function resolveWithoutTransactionKey(WC_Order $order, array $attempt): array
    {
        $attemptNumber = (int) $attempt['attempt_number'];
        $anchor = strtotime((string) ($attempt['updated_at'] ?? ''))
            ?: strtotime((string) ($attempt['created_at'] ?? ''))
            ?: 0;
        $graceEndsAt = $anchor + self::NO_KEY_GRACE_PERIOD;

        if (time() < $graceEndsAt) {
            $order->add_order_note(
                sprintf(
                    __('Klarna capture status check: attempt %1$d has no Buckaroo transaction key. Waiting for the Buckaroo push until %2$s before it can be marked as failed.', 'wc-buckaroo-bpe-gateway'),
                    $attemptNumber,
                    wp_date(wc_date_format() . ' ' . wc_time_format(), $graceEndsAt)
                )
            );

            return $attempt;
        }

        $error = __('No Buckaroo transaction key or push was received for this capture attempt. Verify in Buckaroo Plaza that no capture exists for this order before using Retry capture.', 'wc-buckaroo-bpe-gateway');
        $failed = KlarnaCaptureAttempt::failUnconfirmed($order, $attemptNumber, $error);
        if ($failed === null) {
            $order->add_order_note(
                sprintf(
                    __('Klarna capture status check: attempt %1$d changed in the meantime and was not marked as failed. Check again later.', 'wc-buckaroo-bpe-gateway'),
                    $attemptNumber
                )
            );

            return KlarnaCaptureAttempt::find($order, $attemptNumber) ?? $attempt;
        }

        if (($failed['source'] ?? '') === 'automatic') {
            KlarnaCaptureAttempt::recordAttention($order, $failed);
        } else {
            $order->add_order_note(
                sprintf(
                    __('Klarna capture attempt %1$d was marked as failed: %2$s', 'wc-buckaroo-bpe-gateway'),
                    $attemptNumber,
                    $error
                )
            );
        }

        return $failed;
    }

    private function buckarooClient(): BuckarooClient
    {
        if ($this->buckarooClient === null) {
            $this->buckarooClient = new BuckarooClient((new KlarnaPayGateway())->getMode());
        }

        return $this->buckarooClient;
    }
}
