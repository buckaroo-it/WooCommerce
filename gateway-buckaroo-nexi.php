<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/nexi/nexi.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Nexi extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooNexi::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_nexi';
        $this->title                  = 'Nexi';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Nexi";
        $this->setIcon('24x24/nexi.png', 'new/Nexi.png');

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return $this->processDefaultRefund($order_id, $amount, $reason, true);
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $order = getWCOrder($order_id);
        /** @var BuckarooNexi */
        $nexi = $this->createDebitRequest($order);
        $response = $nexi->Pay();
        return fn_buckaroo_process_response($this, $response);
    }
}
