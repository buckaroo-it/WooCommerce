<?php

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

/**
 * @package Buckaroo
 */
class WC_Push_Buckaroo extends AbstractPaymentGateway
{

    public function __construct()
    {
        parent::__construct();
        fn_buckaroo_process_response_push($this);
        exit;
    }
}
