<?php

namespace Buckaroo\Woocommerce\Gateways\Blik;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class BlikGateway extends AbstractPaymentGateway
{
    public function __construct()
    {
        $this->id = 'buckaroo_blik';
        $this->title = 'Blik';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Blik';
        $this->setIcon('24x24/blik.png', 'svg/blik.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
}