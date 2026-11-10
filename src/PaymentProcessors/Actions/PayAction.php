<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\PaymentProcessors\ReturnProcessor;
use Buckaroo\Woocommerce\ResponseParser\UpgResponseParser;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Adapter\Api;
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
        $result = $this->buckarooClient->process($this->paymentProcessor);

        if ($result->api() === Api::Upg) {
            UpgResponseParser::rememberSale($this->order, (string) $result->reference());

            return ReturnProcessor::forUpg($result, $this->paymentProcessor->gateway, true)->handle($this->paymentProcessor->gateway);
        }

        return $this->finalize($result->raw());
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
