<?php

namespace Buckaroo\Woocommerce\Gateways\MbWay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class MbWayGateway extends AbstractPaymentGateway
{
    public function __construct()
    {
        $this->id = 'buckaroo_mbway';
        $this->title = 'MBWay';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo MBWay';
        $this->setIcon('svg/mbway.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
}
