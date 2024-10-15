<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\SDK\BuckarooClient;
use WC_Order;

class PayAction
{
    protected AbstractPaymentProcessor $paymentProcessor;
    protected BuckarooClient $buckarooClient;
    private WC_Order $order;
    private OrderDetails $orderDetails;

    public function __construct(AbstractPaymentProcessor $gateway, int|string $orderId)
    {
        $this->paymentProcessor = $gateway;
        $this->order = new WC_Order($orderId);
        $this->orderDetails = new OrderDetails($this->order);
        $this->buckarooClient = new BuckarooClient($this->paymentProcessor->gateway->getMode());
    }

    public function process()
    {
        $transactionResponse = $this->buckarooClient->process($this->paymentProcessor);

        return $this->finalize($transactionResponse);
    }

    public function finalize(TransactionResponse $transactionResponse)
    {
        $message = __('Payment unsuccessful. Please try again or choose another payment method.', 'wc-buckaroo-bpe-gateway');

        update_post_meta(
            $this->order->get_id(),
            '_buckaroo_order_in_test_mode',
            $transactionResponse->get('IsTest') == true
        );

        if (method_exists($this->paymentProcessor, 'afterProcessPayment')) {
            $result = $this->paymentProcessor->afterProcessPayment($this->orderDetails, $transactionResponse);

            if (isset($result['on-error-message'])) {
                $message = $result['on-error-message'];
            }

            if (isset($result['redirect'])) {
                return $result;
            }
        }

        if ($transactionResponse->hasRedirect()) {
            return [
                'result' => 'success',
                'redirect' => $transactionResponse->getRedirectUrl(),
            ];
        }

        if (
            $transactionResponse->isSuccess() ||
            $transactionResponse->isAwaitingConsumer() ||
            $transactionResponse->isPendingProcessing()
        ) {
            return [
                'result' => 'success',
                'redirect' => $this->paymentProcessor->gateway->get_return_url($this->order),
            ];
        }

        if ($transactionResponse->isCanceled()) {
            $this->setOrderStatus('cancelled', $transactionResponse->getSomeError());
            return $this->failed(__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'));
        }

        $this->setOrderStatus('failed', $transactionResponse->getSomeError());

        return $this->failed($message);
    }

    private function setOrderStatus(string $status, string $message = '')
    {
        if ($this->order === "pending") {
            return $this->order->update_status($status, $message);
        }

        if (strlen($message)) {
            return $this->order->add_order_note($message);
        }
    }

    private function failed(string $message): array
    {
        wc_add_notice($message, 'error');
        return [
            'result' => 'error',
            'redirect' => wc_get_checkout_url(),
        ];
    }
}