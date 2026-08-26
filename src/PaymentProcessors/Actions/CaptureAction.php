<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateOrderMetaToHpos;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\CaptureRecorder;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Logger;
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

    private array $payload;

    public function __construct(
        AbstractProcessor $paymentProcessor,
        WC_Order $order,
        $captureAmount,
        CaptureAllocation $allocation,
        array $payload,
        ?BuckarooClient $buckarooClient = null
    ) {
        $this->paymentProcessor = $paymentProcessor;
        $this->order = $order;
        $this->captureAmount = (float) $captureAmount;
        $this->allocation = $allocation;
        $this->payload = $payload;
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

        $available = CaptureAllocation::remainingForOrder($this->order);
        if (! $this->allocation->isWithin($available)) {
            return CaptureResult::failed(__('The selected amount is no longer available to capture.', 'wc-buckaroo-bpe-gateway'));
        }

        $response = null;
        try {
            $response = $this->buckarooClient->process($this->paymentProcessor, $this->payload);

            return $this->finalize($response);
        } catch (Throwable $exception) {
            $transactionKey = $response instanceof TransactionResponse
                ? $response->getTransactionKey()
                : null;

            return CaptureResult::unknown($exception->getMessage(), $transactionKey);
        }
    }

    private function finalize(TransactionResponse $response): CaptureResult
    {
        if ($response->isSuccess()) {
            return CaptureRecorder::record(
                $this->order,
                $this->captureAmount,
                $this->order->get_currency(),
                $this->allocation,
                $response->getTransactionKey(),
                $response->toArray()
            );
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
}
