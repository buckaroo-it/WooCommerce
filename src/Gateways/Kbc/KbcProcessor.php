<?php

namespace Buckaroo\Woocommerce\Gateways\Kbc;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class KbcProcessor extends AbstractPaymentProcessor
{
    public function __construct()
    {
        $this->type = 'KBCPaymentButton';
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