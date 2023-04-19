<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/mistercash/mistercash.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Mistercash extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooMisterCash::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_bancontactmrcash';
        $this->title                  = 'Bancontact';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo Bancontact';
        $this->setIcon('24x24/mistercash.png', 'svg/bancontact.svg');

        parent::__construct();
        $this->migrateOldSettings('woocommerce_buckaroo_mistercash_settings');
        $this->addRefundSupport();
        apply_filters('buckaroo_init_payment_class', $this);
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
        /** @var BuckarooMisterCash */
        $mistercash = $this->createDebitRequest($order);

        $response = apply_filters('buckaroo_before_payment_request', $order, $mistercash);
        if ($response['result'] !== 'no_subscription') {
            return $response;
        }

        $response = $mistercash->Pay();
        return fn_buckaroo_process_response($this, $response);

    }
}
