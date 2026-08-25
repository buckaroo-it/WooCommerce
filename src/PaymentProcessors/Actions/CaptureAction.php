<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaKpGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaProcessor;
use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateOrderMetaToHpos;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\KlarnaCaptureAttempt;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Logger;
use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use Throwable;
use WC_Order;

class CaptureAction
{
    protected AbstractProcessor $paymentProcessor;

    protected BuckarooClient $buckarooClient;

    private WC_Order $order;

    private float $captureAmount;

    private CaptureAllocation $allocation;

    private ?int $attemptNumber;

    public function __construct(
        AbstractProcessor $paymentProcessor,
        WC_Order $order,
        $captureAmount,
        CaptureAllocation $allocation,
        ?BuckarooClient $buckarooClient = null,
        ?int $attemptNumber = null
    ) {
        $this->paymentProcessor = $paymentProcessor;
        $this->order = $order;
        $this->captureAmount = (float) $captureAmount;
        $this->allocation = $allocation;
        $this->attemptNumber = $attemptNumber;
        $this->buckarooClient = $buckarooClient ?? new BuckarooClient($paymentProcessor->gateway->getMode());
    }

    public function process(): CaptureResult
    {
        $gateway = $this->paymentProcessor->gateway;

        if (! $gateway->capturable || ! $gateway->canShowCaptureForm($this->order)) {
            return CaptureResult::failed(__('This order cannot be captured', 'wc-buckaroo-bpe-gateway'));
        }

        if ($this->captureAmount <= 0) {
            return CaptureResult::failed(__('A valid capture amount is required', 'wc-buckaroo-bpe-gateway'));
        }

        if (abs($this->allocation->getAmount() - $this->captureAmount) >= 0.01) {
            return CaptureResult::failed(__('Capture amount does not match the selected order items.', 'wc-buckaroo-bpe-gateway'));
        }

        MigrateOrderMetaToHpos::ensureOrderMigrated($this->order);
        $this->order->read_meta_data(true);

        if ($gateway instanceof KlarnaPayGateway && $this->attemptNumber === null) {
            $attempt = KlarnaCaptureAttempt::startManual($this->order, $this->allocation);
            if ($attempt === null) {
                return CaptureResult::failed(__('This capture amount is already claimed or awaiting reconciliation.', 'wc-buckaroo-bpe-gateway'));
            }
            $this->attemptNumber = (int) $attempt['attempt_number'];
        }

        $sendLockKey = 'order';
        $usesKlarnaLock = $gateway instanceof KlarnaPayGateway;
        if (
            $usesKlarnaLock &&
            ! KlarnaCaptureAttempt::acquireLock('capture_send', $this->order, $sendLockKey)
        ) {
            $result = CaptureResult::failed(__('This capture amount is already being processed.', 'wc-buckaroo-bpe-gateway'));
            $this->recordAttemptResult($result);

            return $result;
        }

        $maximumAmount = null;
        if ($gateway instanceof KlarnaPayGateway) {
            $reservedAmount = OrderMeta::get($this->order, KlarnaProcessor::RESERVED_AMOUNT_META_KEY);
            if (is_numeric($reservedAmount)) {
                $maximumAmount = (float) $reservedAmount;
            }
        }

        $available = CaptureAllocation::remainingForOrder($this->order, $maximumAmount);
        if (! $this->allocation->isWithin($available)) {
            if ($usesKlarnaLock) {
                KlarnaCaptureAttempt::releaseLock('capture_send', $this->order, $sendLockKey);
            }

            $result = CaptureResult::failed(__('The selected amount is no longer available to capture.', 'wc-buckaroo-bpe-gateway'));
            $this->recordAttemptResult($result);

            return $result;
        }

        if ($gateway instanceof KlarnaPayGateway) {
            $dataRequestKey = OrderMeta::get($this->order, KlarnaProcessor::DATA_REQUEST_META_KEY);
            if (! is_string($dataRequestKey) || trim($dataRequestKey) === '') {
                KlarnaCaptureAttempt::releaseLock('capture_send', $this->order, $sendLockKey);

                $result = CaptureResult::failed(__('Cannot perform capture, Klarna Data Request key not found', 'wc-buckaroo-bpe-gateway'));
                $this->recordAttemptResult($result);

                return $result;
            }
        }

        $payload = [
            'amountDebit' => number_format($this->captureAmount, 2, '.', ''),
        ];

        $articles = [];
        if ($gateway instanceof KlarnaPayGateway) {
            $articles = (new OrderArticles(new OrderDetails($this->order), $gateway))
                ->get_products_for_capture($this->allocation, $this->captureAmount);
            $payload['articles'] = $articles;
        }

        if (! ($gateway instanceof KlarnaKpGateway) && ! ($gateway instanceof KlarnaPayGateway)) {
            $payload['originalTransactionKey'] = $this->order->get_transaction_id();
        }

        try {
            $response = $this->buckarooClient->process($this->paymentProcessor, $payload);
        } catch (Throwable $exception) {
            $result = CaptureResult::unknown($exception->getMessage());
            $this->recordAttemptResult($result);
            if ($usesKlarnaLock) {
                KlarnaCaptureAttempt::releaseLock('capture_send', $this->order, $sendLockKey);
            }

            return $result;
        }

        $result = $this->finalize($response, $articles);
        $this->recordAttemptResult($result);
        if ($usesKlarnaLock) {
            KlarnaCaptureAttempt::releaseLock('capture_send', $this->order, $sendLockKey);
        }

        return $result;
    }

    public function finalize(TransactionResponse $response, array $products = []): CaptureResult
    {
        $captureAmount = number_format($this->captureAmount, 2, '.', '');
        $order = $this->order;
        $currency = $order->get_currency();
        if ($response && $response->isSuccess()) {
            $transactionKey = $response->getTransactionKey();

            return self::recordSuccessfulCapture(
                $order,
                $captureAmount,
                $currency,
                $this->allocation,
                $transactionKey,
                $response->toArray(),
                $products
            );
        }

        if (
            $response->isPendingProcessing() ||
            $response->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD
        ) {
            return CaptureResult::pending($response->toArray(), $response->getTransactionKey());
        }

        $error = $response->getSomeError();
        if (! empty($error)) {
            $error = is_scalar($error) ? (string) $error : wp_json_encode($error);
            Logger::log(__METHOD__, $error);
            OrderMeta::update($order, '_pushallowed', 'ok');

            return CaptureResult::failed(__('Capture failed: ') . $error, $response->getTransactionKey());
        } else {
            OrderMeta::update($order, '_pushallowed', 'ok');

            return CaptureResult::failed(
                __('Capture failed', 'wc-buckaroo-bpe-gateway'),
                $response->getTransactionKey()
            );
        }
    }

    private function recordAttemptResult(CaptureResult $result): void
    {
        if ($this->attemptNumber === null) {
            return;
        }

        KlarnaCaptureAttempt::updateUnlessSucceeded(
            $this->order,
            $this->attemptNumber,
            [
                'state' => $result->getStatus(),
                'transaction_key' => $result->getTransactionKey(),
                'last_error' => $result->getStatus() === CaptureResult::SUCCEEDED
                    ? ''
                    : sanitize_text_field($result->getMessage()),
            ]
        );
    }

    public static function recordSuccessfulCapture(
        WC_Order $order,
        $captureAmount,
        string $currency,
        CaptureAllocation $allocation,
        string $transactionKey,
        array $responseData = [],
        array $products = []
    ): CaptureResult {
        $lockKey = 'order';
        if (! KlarnaCaptureAttempt::acquireLock('capture_record', $order, $lockKey)) {
            return CaptureResult::pending($responseData, $transactionKey);
        }

        $order->read_meta_data(true);

        if (OrderMeta::get($order, '_capturebuckaroo' . $transactionKey)) {
            KlarnaCaptureAttempt::releaseLock('capture_record', $order, $lockKey);

            return CaptureResult::succeeded($responseData, $transactionKey);
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $captureAmount = number_format((float) $captureAmount, 2, '.', '');
            if (OrderMeta::get($order, '_wc_order_is_captured')) {
                $previousCaptures = (float) OrderMeta::get($order, '_wc_order_amount_captured');
                OrderMeta::update($order, '_wc_order_amount_captured', $previousCaptures + (float) $captureAmount);
            } else {
                OrderMeta::update($order, '_wc_order_is_captured', true);
                OrderMeta::update($order, '_wc_order_amount_captured', $captureAmount);
            }

            $ledger = $allocation->toLedger();
            OrderMeta::add(
                $order,
                '_wc_order_captures',
                [
                'currency' => $currency,
                'id' => $order->get_id() . substr(hash('sha256', $transactionKey), 0, 8),
                'amount' => $captureAmount,
                'line_item_qtys' => $ledger['line_item_qtys'],
                'line_item_totals' => $ledger['line_item_totals'],
                'line_item_tax_totals' => $ledger['line_item_tax_totals'],
                'transaction_id' => $transactionKey,
                ]
            );

            OrderMeta::add($order, '_capturebuckaroo' . $transactionKey, 'ok', true);
            OrderMeta::update($order, '_pushallowed', 'ok');
            $order->add_order_note(
                sprintf(
                    __('Captured %1$s - Capture transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                    $captureAmount . ' ' . $currency,
                    $transactionKey
                )
            );

            if (! empty($products)) {
                OrderMeta::add(
                    $order,
                    'buckaroo_capture',
                    wp_json_encode([
                    'OriginalTransactionKey' => $transactionKey,
                    'products' => $products,
                    ]),
                    false
                );
            }

            $wpdb->query('COMMIT');
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        } finally {
            KlarnaCaptureAttempt::releaseLock('capture_record', $order, $lockKey);
        }

        return CaptureResult::succeeded($responseData, $transactionKey);
    }
}
