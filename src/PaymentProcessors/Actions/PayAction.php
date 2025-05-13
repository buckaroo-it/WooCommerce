<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\PaymentProcessors\ReturnProcessor;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use WC_Order;

class PayAction
{
    protected AbstractPaymentProcessor $paymentProcessor;

    protected BuckarooClient $buckarooClient;

    private WC_Order $order;

    private OrderDetails $orderDetails;

    public function __construct(AbstractPaymentProcessor $gateway, $orderId)
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
        return (new ReturnProcessor($transactionResponse->toArray(), false))->handle($this->paymentProcessor->gateway);
    }

    private function setOrderStatus(string $status, string $message = '')
    {
        if ($this->order === 'pending') {
            return $this->order->update_status($status, $message);
        }

        if (strlen($message)) {
            return $this->order->add_order_note($message);
        }
    }
}
