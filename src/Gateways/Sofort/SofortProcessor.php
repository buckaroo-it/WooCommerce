<?php

namespace Buckaroo\Woocommerce\Gateways\Sofort;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class SofortProcessor extends AbstractPaymentProcessor
{
    /**
     * @access public
     */
    public function __construct()
    {
        $this->type = 'sofortueberweisung';
        $this->version = 1;
    }
}