<?php

namespace Buckaroo\Woocommerce\Gateways\Blik;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class BlikProcessor extends AbstractPaymentProcessor
{
    public $channel;

    /**
     * @access public
     */
    public function __construct()
    {
        $this->type = 'blik';
        $this->version = 0;
    }
}