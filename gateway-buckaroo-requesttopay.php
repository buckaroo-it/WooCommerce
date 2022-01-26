<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/requesttopay/requesttopay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_RequestToPay extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooRequestToPay::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_requesttopay';
        $this->title                  = 'Request To Pay';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Request To Pay";
        $this->setIcon('24x24/requesttopay.png', 'new/RequestToPay.png');

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
        /** @var BuckarooRequestToPay */
        $rtp = $this->createDebitRequest($order);
        $order_details = new Buckaroo_Order_Details($order_id);
       
        $response = $rtp->Pay(
            array(
                'CustomerFirstName' => $order_details->getBilling('first_name'),
                'CustomerLastName' => $order_details->getBilling('last_name')
            )
        );
        return fn_buckaroo_process_response($this, $response);
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {

    }
}
