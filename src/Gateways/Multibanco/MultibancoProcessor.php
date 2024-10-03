<?php

namespace Buckaroo\Woocommerce\Gateways\Multibanco;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class MultibancoProcessor extends AbstractPaymentProcessor
{
    public function __construct()
    {
        $this->type = 'Multibanco';
    }
}