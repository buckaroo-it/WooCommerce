<?php

namespace Buckaroo\Woocommerce\Gateways;

use Buckaroo\Woocommerce\Order\OrderDetails;
use WC_Order;
use WC_Payment_Gateway;

class AbstractRefundProcessor extends AbstractProcessor
{

    protected OrderDetails $order_details;

    private float $amount;

    private string $reason;

    public function __construct(
        AbstractPaymentGateway $gateway,
        OrderDetails           $order_details,
        float                  $amount,
        string                 $reason
    )
    {
        $this->gateway = $gateway;
        $this->order_details = $order_details;
        $this->amount = $amount;
        $this->reason = $reason;
    }

    /**
     * Get request action
     *
     * @return string
     */
    public function getAction(): string
    {
        return 'refund';
    }


    /**
     * Get payment code required for sdk
     *
     * @return string
     */
    public function getServiceCode(): string
    {
        return $this->gateway->getServiceCode();
    }

    /**
     * Get request body
     *
     * @return array
     */
    public function getBody(): array
    {
        return array_merge(
            $this->getMethodBody(),
            [
                'order' => (string)$this->getOrder()->get_id(),
                'invoice' => $this->get_invoice_number(),
                'amountCredit' => number_format((float)$this->amount, 2, '.', ''),
                'currency' => get_woocommerce_currency(),
                'returnURL' => $this->get_return_url(),
                'cancelURL' => $this->get_return_url(),
                'pushURL' => $this->get_push_url(),
                'originalTransactionKey' => (string)$this->getOrder()->get_transaction_id('edit'),
                'additionalParameters' => [
                    'real_order_id' => $this->getOrder()->get_id(),
                ],

                'description' => $this->reason,
                'clientIP' => $this->getIp(),
            ]
        );
    }

    protected function getMethodBody(): array
    {
        return array();
    }

    /**
     * Get order
     *
     * @return WC_Order
     */
    protected function getOrder(): WC_Order
    {
        return $this->order_details->get_order();
    }

    private function get_invoice_number(): string
    {
        if (in_array($this->gateway->id, ["buckaroo_afterpaynew", "buckaroo_afterpay"])) {
            return (string)$this->getOrder()->get_order_number() . time();
        }
        return (string)$this->getOrder()->get_order_number();
    }

    /**
     * Get return url
     *
     * @return string
     */
    public function get_return_url($order_id = null)
    {
        return add_query_arg('wc-api', 'wc_buckaroo_return', home_url('/'));
    }

    /**
     * Get push url
     *
     * @return string
     */
    private function get_push_url(): string
    {
        return add_query_arg('wc-api', 'wc_push_buckaroo', home_url('/'));
    }
}
