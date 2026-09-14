<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\CaptureRecorder;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\NamedLock;
use RuntimeException;
use WC_Order;

class KlarnaPushProcessor
{
    private const PAY_TRANSACTION_TYPE = 'C339';

    private const SETTLEMENT_LOCK_WAIT_SECONDS = 5;

    public static function handle($handled, WC_Order $order, ResponseParser $responseParser): bool
    {
        if ($handled) {
            return true;
        }

        if (self::reconcileCapture($order, $responseParser)) {
            return true;
        }

        if (
            $order->get_payment_method() !== 'buckaroo_klarnapay' ||
            strcasecmp((string) $responseParser->getActionCode(), 'CancelReservation') !== 0
        ) {
            return false;
        }

        if ($responseParser->isSuccess()) {
            $order->add_order_note(
                __('Klarna reservation cancellation confirmed (push received).', 'wc-buckaroo-bpe-gateway')
            );
        } else {
            $order->add_order_note(
                sprintf(
                    __('Klarna reservation cancellation push reported a failure: %s', 'wc-buckaroo-bpe-gateway'),
                    $responseParser->getSubCodeMessage() ?: ''
                )
            );
        }
        OrderMeta::add($order, '_pushallowed', 'ok', true);

        return true;
    }

    public static function handleReservation($context, WC_Order $order, ResponseParser $responseParser)
    {
        if ($context !== null || $order->get_status() === 'cancelled') {
            return $context;
        }

        if ($responseParser->getServiceParameter('reservationNumber') !== null) {
            $order->payment_complete($responseParser->getTransactionKey());
            $order->add_order_note('Payment successfully reserved');
            $order->add_meta_data('buckaroo_is_reserved', 'yes', true);
            $order->save_meta_data();

            return [
                'transaction' => $responseParser->getDataRequest(),
                'completed_order' => false,
            ];
        }

        if (
            $order->get_payment_method() !== 'buckaroo_klarnapay' ||
            $order->get_meta('buckaroo_is_reserved') === 'yes'
        ) {
            return null;
        }

        $order->set_transaction_id($responseParser->getTransactionKey());
        $order->update_status(
            'on-hold',
            __('Klarna reservation authorized; awaiting capture.', 'wc-buckaroo-bpe-gateway')
        );
        $order->add_meta_data('buckaroo_is_reserved', 'yes', true);
        $order->update_meta_data('_wc_order_authorized', 'yes');
        $order->update_meta_data(
            KlarnaProcessor::RESERVED_AMOUNT_META_KEY,
            number_format($responseParser->getAmount() ?? $order->get_total('edit'), 2, '.', '')
        );

        $dataRequestKey = $responseParser->getDataRequest();
        if (is_string($dataRequestKey) && strlen($dataRequestKey) > 0) {
            $order->update_meta_data(KlarnaProcessor::DATA_REQUEST_META_KEY, $dataRequestKey);
        }

        $order->save_meta_data();
        $order->save();

        return [
            'transaction' => $responseParser->getDataRequest(),
            'completed_order' => false,
        ];
    }

    public static function reconcileCapture(WC_Order $order, ResponseParser $responseParser): bool
    {
        if (
            $order->get_payment_method() !== 'buckaroo_klarnapay' ||
            ! self::isPay($responseParser) ||
            strcasecmp((string) $responseParser->getPaymentMethod(), 'klarna') !== 0 ||
            strcasecmp((string) $responseParser->getCurrency(), $order->get_currency()) !== 0
        ) {
            return false;
        }

        $transactionKey = (string) $responseParser->getTransactionKey();
        $attempt = self::findCaptureAttempt($order, $responseParser, $transactionKey);
        if ($attempt === null) {
            return false;
        }

        $attemptNumber = (int) $attempt['attempt_number'];
        if (
            $attempt['state'] === CaptureResult::SUCCEEDED &&
            ! CaptureResult::isSuccessStatusCode($responseParser->getStatusCode())
        ) {
            return true;
        }

        if (
            $responseParser->isPendingProcessing() ||
            CaptureResult::isPendingStatusCode($responseParser->getStatusCode())
        ) {
            KlarnaCaptureAttempt::updateUnlessSucceeded(
                $order,
                $attemptNumber,
                [
                    'state' => CaptureResult::PENDING,
                    'transaction_key' => $transactionKey ?: null,
                ]
            );

            return true;
        }

        if (CaptureResult::isSuccessStatusCode($responseParser->getStatusCode())) {
            return self::reconcileSuccessfulCapture($order, $responseParser, $attempt, $transactionKey);
        }

        $failedAttempt = KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            $attemptNumber,
            [
                'state' => CaptureResult::FAILED,
                'transaction_key' => $transactionKey ?: null,
                'last_error' => sanitize_text_field(
                    $responseParser->getSubCodeMessage() ?: __('Capture failed', 'wc-buckaroo-bpe-gateway')
                ),
            ]
        );
        if ($failedAttempt !== null) {
            KlarnaCaptureAttempt::recordAttention($order, $failedAttempt);
        }

        return true;
    }

    private static function isPay(ResponseParser $responseParser): bool
    {
        $action = $responseParser->getActionCode();
        if (is_string($action) && trim($action) !== '') {
            return strcasecmp($action, 'Pay') === 0;
        }

        return strcasecmp(
            (string) $responseParser->getTransactionType(),
            self::PAY_TRANSACTION_TYPE
        ) === 0;
    }

    private static function reconcileSuccessfulCapture(
        WC_Order $order,
        ResponseParser $responseParser,
        array $attempt,
        string $transactionKey
    ): bool {
        $attemptNumber = (int) $attempt['attempt_number'];
        $preparedAttempt = KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            $attemptNumber,
            [
                'state' => KlarnaCaptureAttempt::IN_PROGRESS,
                'transaction_key' => $transactionKey,
            ]
        );
        if ($preparedAttempt === null) {
            throw new RuntimeException(
                __('Klarna capture success could not be prepared for local reconciliation.', 'wc-buckaroo-bpe-gateway')
            );
        }
        $attempt = $preparedAttempt;
        $storedAllocation = $attempt['allocation'];
        $allocation = CaptureAllocation::fromArrays(
            $storedAllocation['line_item_qtys'],
            $storedAllocation['line_item_totals'],
            $storedAllocation['line_item_tax_totals']
        );
        $amount = (float) $attempt['amount'];
        $gateway = new KlarnaPayGateway();
        $products = (new KlarnaOrderArticles(new OrderDetails($order), $gateway))
            ->get_products_for_capture($allocation, $amount);

        $captureResult = CaptureRecorder::record(
            $order,
            $amount,
            $attempt['currency'],
            $allocation,
            $transactionKey,
            $responseParser->get(),
            $products
        );
        if (! $captureResult->isSuccess()) {
            $recordingAttempt = KlarnaCaptureAttempt::updateUnlessSucceeded(
                $order,
                (int) $attempt['attempt_number'],
                [
                    'state' => $captureResult->getStatus(),
                    'transaction_key' => $transactionKey,
                    'last_error' => sanitize_text_field($captureResult->getMessage()),
                ]
            );
            if ($recordingAttempt === null) {
                throw new RuntimeException(
                    __('Klarna capture recording failure could not be persisted for retry.', 'wc-buckaroo-bpe-gateway')
                );
            }
            KlarnaCaptureAttempt::recordAttention($order, $recordingAttempt);
            KlarnaFulfillmentActions::scheduleStatusCheck($order, $recordingAttempt);

            return true;
        }

        $updatedAttempt = KlarnaCaptureAttempt::updateUnlessSucceeded(
            $order,
            $attemptNumber,
            [
                'state' => CaptureResult::SUCCEEDED,
                'transaction_key' => $transactionKey,
                'last_error' => '',
            ]
        );
        if ($updatedAttempt === null) {
            throw new RuntimeException(
                __('Klarna capture success could not be finalized in the attempt ledger.', 'wc-buckaroo-bpe-gateway')
            );
        }
        $reservedAmount = OrderMeta::get($order, KlarnaProcessor::RESERVED_AMOUNT_META_KEY);
        $captureTarget = is_numeric($reservedAmount)
            ? (float) $reservedAmount
            : (float) $order->get_total('edit');
        $capturedAmount = (float) OrderMeta::get($order, '_wc_order_amount_captured');
        if (Helper::roundAmount($capturedAmount) >= Helper::roundAmount($captureTarget)) {
            $order->payment_complete($transactionKey);
        }
        $order->save();
        self::updateSettlementMeta($order, $responseParser, $amount);
        OrderMeta::add($order, '_payment_method_transaction', 'klarna', true);
        OrderMeta::add($order, '_pushallowed', 'ok', true);
        KlarnaCaptureAttempt::clearAttention($order);

        return true;
    }

    private static function findCaptureAttempt(
        WC_Order $order,
        ResponseParser $responseParser,
        string $transactionKey
    ): ?array {
        $attempts = array_reverse(KlarnaCaptureAttempt::all($order));
        foreach ($attempts as $attempt) {
            if ($transactionKey !== '' && ($attempt['transaction_key'] ?? null) === $transactionKey) {
                return $attempt;
            }
        }

        if ($transactionKey === '' || $responseParser->getAmount() === null) {
            return null;
        }

        $activeMatches = [];
        $failedMatches = [];
        foreach ($attempts as $attempt) {
            if (
                ! in_array(
                    $attempt['state'],
                    [
                        KlarnaCaptureAttempt::IN_PROGRESS,
                        CaptureResult::PENDING,
                        CaptureResult::UNKNOWN,
                        CaptureResult::FAILED,
                    ],
                    true
                ) ||
                ! empty($attempt['transaction_key']) ||
                strcasecmp((string) ($attempt['currency'] ?? ''), (string) $responseParser->getCurrency()) !== 0
            ) {
                continue;
            }

            if (abs((float) $attempt['amount'] - $responseParser->getAmount()) < 0.01) {
                if ($attempt['state'] === CaptureResult::FAILED) {
                    $failedMatches[] = $attempt;
                } else {
                    $activeMatches[] = $attempt;
                }
            }
        }

        if (count($activeMatches) > 0) {
            return count($activeMatches) === 1 ? $activeMatches[0] : null;
        }

        return count($failedMatches) === 1 ? $failedMatches[0] : null;
    }

    private static function updateSettlementMeta(
        WC_Order $order,
        ResponseParser $responseParser,
        float $paidAmount
    ): void {
        $lockKey = 'order';
        if (! NamedLock::acquire('settlement', $order, $lockKey, self::SETTLEMENT_LOCK_WAIT_SECONDS)) {
            throw new RuntimeException(
                __('Klarna capture settlement could not be recorded locally.', 'wc-buckaroo-bpe-gateway')
            );
        }

        try {
            $order->read_meta_data(true);
            $transactionKey = $responseParser->getRelatedTransactionPartialPayment()
                ?? $responseParser->getTransactionKey();
            $settlements = OrderMeta::get($order, 'buckaroo_settlement');
            if (! is_array($settlements)) {
                $settlements = [];
            }

            $settlements[$transactionKey] = $paidAmount;
            OrderMeta::update($order, 'buckaroo_settlement', $settlements);
        } finally {
            NamedLock::release('settlement', $order, $lockKey);
        }
    }
}
