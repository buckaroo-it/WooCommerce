<?php

namespace Buckaroo\Woocommerce\Gateways\Payconiq;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class PayconiqProcessor extends AbstractPaymentProcessor
{
    public function __construct()
    {
        $this->type = 'payconiq';
        $this->version = 1;
    }
}