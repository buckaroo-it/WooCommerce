<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/kbc/kbc.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_KBC extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooKBC::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_kbc';
        $this->title                  = 'KBC';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo KBC";
        $this->setIcon('24x24/kbc.png', 'new/KBC.png', 'svg/KBC.svg');
        
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
        return $this->processDefaultRefund($order_id, $amount, $reason);
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        $order = getWCOrder($order_id);
        /** @var BuckarooKBC */
        $kbc = $this->createDebitRequest($order);
        $response = $kbc->Pay();
        return fn_buckaroo_process_response($this, $response);
    }
}
