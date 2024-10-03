<?php

namespace Buckaroo\Woocommerce\Gateways\MbWay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class MbWayProcessor extends AbstractPaymentProcessor
{
    public function __construct()
    {
        $this->type = 'MBWay';
    }
}