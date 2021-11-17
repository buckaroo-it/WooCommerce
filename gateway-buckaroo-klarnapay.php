<?php

require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/klarna/klarna.php');
require_once(dirname(__FILE__) . '/gateway-buckaroo-klarna.php');

class WC_Gateway_Buckaroo_KlarnaPay extends WC_Gateway_Buckaroo_Klarna {
    function __construct() {
        $this->id = 'buckaroo_klarnapay';
        $this->title = 'Klarna: Pay later';
        $this->method_title = 'Buckaroo Klarna Pay later';
        $this->description =  sprintf(__('Pay with %s', 'wc-buckaroo-bpe-gateway'), $this->title);

        $this->klarnaPaymentFlowId = 'pay';

        parent::__construct();

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

        } else {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_wc_gateway_buckaroo_klarnapay', array( $this, 'response_handler' ) );
            $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_KlarnaPay', $this->notify_url);
        }
    }
    /**
     * Payment form on checkout page
     * 
     * @return void
     */
    public function payment_fields()
    {
        $this->renderTemplate();
    }
}
