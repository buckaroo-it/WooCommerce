<?php

namespace Buckaroo\Woocommerce\PaymentProcessors;


use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class PushProcessor extends AbstractPaymentGateway
{
    public function __construct()
    {
        parent::__construct();
        fn_buckaroo_process_response_push($this);
        exit;
    }
}