<?php

namespace WC_Buckaroo\WooCommerce\PaymentProcessing;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentGatewayHandler;

class PushService extends PaymentGatewayHandler
{
    public function __construct()
    {
        parent::__construct();
        fn_buckaroo_process_response_push($this);
        exit;
    }
}