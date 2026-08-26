<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateOrderMetaToHpos;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\CaptureRecorder;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\NamedLock;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use Throwable;
use WC_Order;

class KlarnaCaptureAction
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

        if ($this->attemptNumber === null) {
            $attempt = KlarnaCaptureAttempt::startManual($this->order, $this->allocation);
            if ($attempt === null) {
                return CaptureResult::failed(__('This capture amount is already claimed or awaiting reconciliation.', 'wc-buckaroo-bpe-gateway'));
            }
            $this->attemptNumber = (int) $attempt['attempt_number'];
        }

        $sendLockKey = 'order';
        if (! NamedLock::acquire('capture_send', $this->order, $sendLockKey)) {
            $result = CaptureResult::failed(__('This capture amount is already being processed.', 'wc-buckaroo-bpe-gateway'));
            $this->recordAttemptResult($result);

            return $result;
        }

        try {
            $reservedAmount = OrderMeta::get($this->order, KlarnaProcessor::RESERVED_AMOUNT_META_KEY);
            $maximumAmount = is_numeric($reservedAmount) ? (float) $reservedAmount : null;
            $available = CaptureAllocation::remainingForOrder($this->order, $maximumAmount);
            if (! $this->allocation->isWithin($available)) {
                $result = CaptureResult::failed(__('The selected amount is no longer available to capture.', 'wc-buckaroo-bpe-gateway'));
                $this->recordAttemptResult($result);

                return $result;
            }

            $dataRequestKey = OrderMeta::get($this->order, KlarnaProcessor::DATA_REQUEST_META_KEY);
            if (! is_string($dataRequestKey) || trim($dataRequestKey) === '') {
                $result = CaptureResult::failed(__('Cannot perform capture, Klarna Data Request key not found', 'wc-buckaroo-bpe-gateway'));
                $this->recordAttemptResult($result);

                return $result;
            }

            $articles = (new KlarnaOrderArticles(new OrderDetails($this->order), $gateway))
                ->get_products_for_capture($this->allocation, $this->captureAmount);
            $payload = [
                'amountDebit' => number_format($this->captureAmount, 2, '.', ''),
                'articles' => $articles,
            ];

            $response = null;
            try {
                $response = $this->buckarooClient->process($this->paymentProcessor, $payload);
                $result = $this->finalize($response, $articles);
            } catch (Throwable $exception) {
                $transactionKey = $response instanceof TransactionResponse
                    ? $response->getTransactionKey()
                    : null;
                $result = CaptureResult::unknown($exception->getMessage(), $transactionKey);
            }

            $this->recordAttemptResult($result);

            return $result;
        } finally {
            NamedLock::release('capture_send', $this->order, $sendLockKey);
        }
    }

    private function finalize(TransactionResponse $response, array $products): CaptureResult
    {
        if ($response->isSuccess()) {
            return CaptureRecorder::record(
                $this->order,
                $this->captureAmount,
                $this->order->get_currency(),
                $this->allocation,
                $response->getTransactionKey(),
                $response->toArray(),
                $products
            );
        }

        if (CaptureResult::isPendingStatusCode($response->getStatusCode())) {
            return CaptureResult::pending($response->toArray(), $response->getTransactionKey());
        }

        $error = $response->getSomeError();
        if (! empty($error)) {
            $error = is_scalar($error) ? (string) $error : wp_json_encode($error);
            Logger::log(__METHOD__, $error);
            OrderMeta::update($this->order, '_pushallowed', 'ok');

            return CaptureResult::failed(__('Capture failed: ') . $error, $response->getTransactionKey());
        }

        OrderMeta::update($this->order, '_pushallowed', 'ok');

        return CaptureResult::failed(
            __('Capture failed', 'wc-buckaroo-bpe-gateway'),
            $response->getTransactionKey()
        );
    }

    private function recordAttemptResult(CaptureResult $result): void
    {
        if ($this->attemptNumber === null) {
            return;
        }

        $attempt = KlarnaCaptureAttempt::updateUnlessSucceeded(
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
        if (($attempt['source'] ?? '') !== 'automatic') {
            return;
        }
        if (in_array($attempt['state'], [CaptureResult::FAILED, CaptureResult::UNKNOWN], true)) {
            KlarnaCaptureAttempt::recordAttention($this->order, $attempt);
        } elseif ($attempt['state'] === CaptureResult::SUCCEEDED) {
            KlarnaCaptureAttempt::clearAttention($this->order);
        }
    }
}
