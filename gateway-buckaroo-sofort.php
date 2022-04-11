<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/sofortbanking/sofortbanking.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Sofortbanking extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooSofortbanking::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_sofortueberweisung';
        $this->title                  = 'Sofort';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Sofort";
        $this->setIcon('24x24/sofort.png', 'new/Sofort.png', 'svg/Sofort.svg');
        
        $this->migrateOldSettings('woocommerce_buckaroo_sofortbanking_settings');
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
        /** @var BuckarooSofortbanking */
        $sofortbanking = $this->createDebitRequest($order);
        $response = $sofortbanking->Pay();

        return fn_buckaroo_process_response($this, $response);
    }

}
