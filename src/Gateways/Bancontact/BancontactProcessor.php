<?php

namespace Buckaroo\Woocommerce\Gateways\Bancontact;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class BancontactProcessor extends AbstractPaymentProcessor
{
    /**
     * @access public
     */
    public function __construct()
    {
        $this->type = 'bancontactmrcash';
        $this->version = 1;
    }
}