<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/p24/p24.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_P24 extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooP24::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_przelewy24';
        $this->title                  = 'P24';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo P24";
        $this->setIcon('24x24/p24.png', 'new/Przelewy24.png');
        $this->migrateOldSettings('woocommerce_buckaroo_p24_settings');
        
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
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        $order = getWCOrder($order_id);
        /** @var BuckarooP24 */
        $p24 = $this->createDebitRequest($order);
        $get_shipping_first_name         = getWCOrderDetails($order_id, 'billing_first_name');
        $get_shipping_last_name          = getWCOrderDetails($order_id, 'billing_last_name');
        $get_shipping_email              = getWCOrderDetails($order_id, 'billing_email');
        $customVars['Customeremail']     = !empty($get_shipping_email) ? $get_shipping_email : '';
        $customVars['CustomerFirstName'] = !empty($get_shipping_first_name) ? $get_shipping_first_name : '';
        $customVars['CustomerLastName']  = !empty($get_shipping_last_name) ? $get_shipping_last_name : '';
        $response = $p24->Pay($customVars);
        return fn_buckaroo_process_response($this, $response);
    }
}
