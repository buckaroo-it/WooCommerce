<?php

namespace Buckaroo\Woocommerce\Gateways\Eps;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class EpsProcessor extends AbstractPaymentProcessor
{
    public function __construct()
    {
        $this->type = 'eps';
        $this->version = 1;
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = array())
    {
        return parent::Pay();
    }
}