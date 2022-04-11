<?php

require_once(dirname(__FILE__) . '/library/api/paymentmethods/ideal/ideal.php');

/**
* @package Buckaroo
*/
class WC_Gateway_Buckaroo_Ideal extends WC_Gateway_Buckaroo {

    const PAYMENT_CLASS = BuckarooIDeal::class;
    function __construct() {
        $this->id = 'buckaroo_ideal';
        $this->title = 'iDEAL';
        $this->has_fields   = true;
        $this->method_title = "Buckaroo iDEAL";
        $this->setIcon('24x24/ideal.png', 'new/iDEAL.png', 'svg/iDEAL.svg');

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
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        return $this->processDefaultRefund($order_id, $amount, $reason);
    }
    
    /**
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
    public function validate_fields() { 
        if ( !isset( $_POST['buckaroo-ideal-issuer'] ) || !$_POST['buckaroo-ideal-issuer'] || empty($_POST['buckaroo-ideal-issuer']) ) {
            wc_add_notice( __("<strong>iDEAL bank </strong> is a required field.", 'wc-buckaroo-bpe-gateway'), 'error' );
        }

        parent::validate_fields();
    }
    
    /**
     * Process payment
     * 
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    function process_payment($order_id) {  
        if ( !isset( $_POST['buckaroo-ideal-issuer'] ) || !$_POST['buckaroo-ideal-issuer'] || empty($_POST['buckaroo-ideal-issuer']) ) {
            wc_add_notice( __("<strong>iDEAL bank </strong> is a required field.", 'wc-buckaroo-bpe-gateway'), 'error' );
            return;
        }

        $order = getWCOrder($order_id);
        /** @var BuckarooIDeal */
        $ideal = $this->createDebitRequest($order);
        $ideal->issuer =  $_POST['buckaroo-ideal-issuer'];
        $response = $ideal->Pay();            
        return fn_buckaroo_process_response($this, $response);
    }
}
