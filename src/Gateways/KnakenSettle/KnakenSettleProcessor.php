<?php

namespace Buckaroo\Woocommerce\Gateways\KnakenSettle;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class KnakenSettleProcessor extends AbstractPaymentProcessor
{
    public function __construct()
    {
        $this->type = 'knaken';
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