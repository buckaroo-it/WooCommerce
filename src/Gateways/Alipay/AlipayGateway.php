<?php

namespace Buckaroo\Woocommerce\Gateways\Alipay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class AlipayGateway extends AbstractPaymentGateway
{
    public function __construct()
    {
        $this->id = 'buckaroo_alipay';
        $this->title = 'Alipay';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Alipay';
        $this->setIcon('svg/alipay.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
}
