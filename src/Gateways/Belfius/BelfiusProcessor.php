<?php

namespace Buckaroo\Woocommerce\Gateways\Belfius;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class BelfiusProcessor extends AbstractPaymentProcessor
{
    /**
     * @access public
     */
    public function __construct()
    {
        $this->type = 'belfius';
        $this->version = 0;
    }
}