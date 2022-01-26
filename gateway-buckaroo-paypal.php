<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/buckaroopaypal/buckaroopaypal.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Paypal extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooPayPal::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_paypal';
        $this->title                  = 'Buckaroo PayPal';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo PayPal";
        $this->setIcon('24x24/paypal.gif', 'new/PayPal.png');

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
        /** @var BuckarooPayPal */
        $paypal = $this->createDebitRequest($order);
        $order_details = new Buckaroo_Order_Details($order);
        
        $customVars = array(
            'CustomerLastName' => $order_details->getBilling('last_name')
        );
        if ($this->sellerprotection == 'TRUE') {
            $paypal->sellerprotection = 1;
            $address =  $order_details->getBillingAddressComponents();

            $customVars = array_merge(
                $customVars,
                array(
                   'ShippingPostalCode' => $order_details->getShipping('postcode'),
                   'ShippingCity'       => $order_details->getShipping('city'),
                   'ShippingStreet'     => $address['street'],
                   'ShippingHouse'      => $address['house_number'],
                   'StateOrProvince'    => $order_details->getBilling('state'),
                   'Country'            => $order_details->getBilling('country')
                )
            );
        }
        $response = $paypal->Pay($customVars);

        return fn_buckaroo_process_response($this, $response);
    }
    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['sellerprotection'] = array(
            'title'       => __('Seller Protection', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Sends customer address information to PayPal to enable PayPal seller protection.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Enabled', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('Disabled', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'TRUE');
    }
}
