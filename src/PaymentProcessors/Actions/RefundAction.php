<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\NamedLock;
use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use RuntimeException;
use WC_Order;
use WP_Error;

class RefundAction
{
    protected ?string $originalTransactionKey;

    protected AbstractRefundProcessor $paymentProcessor;

    protected BuckarooClient $buckarooClient;

    private WC_Order $order;

    public function __construct(AbstractRefundProcessor $paymentProcessor, $orderId, ?string $originalTransactionKey)
    {
        $this->paymentProcessor = $paymentProcessor;
        $this->order = new WC_Order($orderId);
        $this->buckarooClient = new BuckarooClient($this->paymentProcessor->gateway->getMode());
        $this->originalTransactionKey = $originalTransactionKey;
    }

    public static function initiateExternalServiceRefund($order_id, ResponseParser $responseParser)
    {
        Logger::log('PUSH', 'Refund payment PUSH received ' . $responseParser->get('coreStatus'));
        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            exit();
        }

        if (! NamedLock::acquire(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $order, PushProcessor::SETTLEMENT_LOCK_KEY, PushProcessor::SETTLEMENT_LOCK_WAIT_SECONDS)) {
            throw new RuntimeException(
                __('Buckaroo push could not be processed because the order is locked.', 'wc-buckaroo-bpe-gateway')
            );
        }

        try {
            $order->read_meta_data(true);
            $allowedPush = OrderMeta::get($order, '_pushallowed');
            Logger::log(__METHOD__ . '|10|', $allowedPush);
            if ($responseParser->isSuccess() && $allowedPush == 'ok') {
                $tmp = OrderMeta::get($order, '_refundbuckaroo' . $responseParser->getTransactionKey());
                if (empty($tmp)) {
                    OrderMeta::add($order, '_refundbuckaroo' . $responseParser->getTransactionKey(), 'ok', true);
                    wc_create_refund(
                        [
                            'amount' => $responseParser->getAmountCredit(),
                            'reason' => __('Refunded', 'wc-buckaroo-bpe-gateway'),
                            'order_id' => $order_id,
                            'line_items' => [],
                        ]
                    );
                }
            }
        } finally {
            NamedLock::release(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $order, PushProcessor::SETTLEMENT_LOCK_KEY);
        }
        exit();
    }

    public function process()
    {
        $transactionResponse = $this->buckarooClient->process(
            $this->paymentProcessor,
            $this->originalTransactionKey ? ['originalTransactionKey' => $this->originalTransactionKey] : []
        );

        return $this->finalize($transactionResponse);
    }

    public function finalize(TransactionResponse $transactionResponse)
    {
        if ($transactionResponse->isSuccess()) {
            $this->order->add_order_note(
                sprintf(
                    __('Refunded %1$s - Refund transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                    wc_price($transactionResponse->get('AmountCredit')),
                    $transactionResponse->getTransactionKey()
                )
            );
            OrderMeta::add(
                $this->order,
                '_refundbuckaroo' . $transactionResponse->getTransactionKey(),
                'ok',
                true
            );

            return true;
        }

        if ($this->isPendingRefund($transactionResponse)) {
            $this->order->add_order_note(
                sprintf(
                    __('Refund of %1$s is pending processing - Transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                    wc_price($transactionResponse->get('AmountCredit')),
                    $transactionResponse->getTransactionKey()
                )
            );
            OrderMeta::add(
                $this->order,
                '_refundbuckaroo' . $transactionResponse->getTransactionKey(),
                'ok',
                true
            );

            return true;
        }

        $this->order->add_order_note(
            sprintf(
                __(
                    'Refund failed for transaction ID: %s ' . "\n" . $transactionResponse->getSomeError(),
                    'wc-buckaroo-bpe-gateway'
                ),
                $this->order->get_transaction_id()
            )
        );

        return new WP_Error('error_refund', __('Refund failed: ', 'wc-buckaroo-bpe-gateway') . $transactionResponse->getSomeError());
    }

    private function isPendingRefund(TransactionResponse $transactionResponse): bool
    {
        $statusCode = $transactionResponse->getStatusCode();

        return $transactionResponse->isPendingProcessing()
            || $statusCode == ResponseStatus::BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD;
    }
}
