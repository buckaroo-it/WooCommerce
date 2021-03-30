<?php

require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/klarna/klarna.php');
require_once(dirname(__FILE__) . '/gateway-buckaroo-klarna.php');

class WC_Gateway_Buckaroo_KlarnaPII extends WC_Gateway_Buckaroo_Klarna {
    function __construct() {
        $this->id = 'buckaroo_klarnapii';
        $this->title = 'Klarna: Slice it';
        $this->method_title = 'Buckaroo Klarna Slice it';
        $this->description = "Betaal met Klarna Slice it";

        $this->klarnaPaymentFlowId = 'PayInInstallments';
        $this->klarnaSelector = 'buckaroo_' . $this->id;

        parent::__construct();

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

        } else {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_wc_gateway_buckaroo_klarnapii', array( $this, 'response_handler' ) );
            $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_KlarnaPII', $this->notify_url);
        }
    }
}
