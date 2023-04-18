<?php

require_once(dirname(__FILE__) . '/library/api/paymentmethods/ideal/ideal.php');

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Ideal extends WC_Gateway_Buckaroo
{

    const PAYMENT_CLASS = BuckarooIDeal::class;
    use WC_Buckaroo_Subscriptions_Trait;
    public function __construct()
    {
        $this->id = 'buckaroo_ideal';
        $this->title = 'iDEAL';
        $this->has_fields   = true;
        $this->method_title = "Buckaroo iDEAL";
        $this->setIcon('24x24/ideal.png', 'svg/ideal.svg');

        parent::__construct();
        $this->addRefundSupport();
        $this->addSubscriptionsSupport();
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
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        $issuer = $this->request('buckaroo-ideal-issuer');

        if ($issuer === null) {
            wc_add_notice(__("<strong>iDEAL bank </strong> is a required field.", 'wc-buckaroo-bpe-gateway'), 'error');
        } else
        if (!in_array($issuer, array_keys(BuckarooIDeal::getIssuerList()))) {
            wc_add_notice(__("A valid iDEAL bank is required.", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        parent::validate_fields();
    }

    /**
     * Process payment
     * 
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    function process_payment($order_id)
    {
        $order = getWCOrder($order_id);
        /** @var BuckarooIDeal */
        $ideal = $this->createDebitRequest($order);
        $ideal->issuer = $this->request('buckaroo-ideal-issuer');

        if($this->is_subscriptions_enabled() && $this->has_subscription($order_id)){
            if($this->is_not_trial_subscription( $order ))
                return apply_filters('buckaroo_subscriptions', $order, $ideal);
        }else{
            $response = $ideal->Pay();
            return fn_buckaroo_process_response($this, $response);
        }
    }
}
