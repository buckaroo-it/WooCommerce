<?php

namespace Buckaroo\Woocommerce\Gateways\WeChatPay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class WeChatPayGateway extends AbstractPaymentGateway
{
    public function __construct()
    {
        $this->id = 'buckaroo_wechatpay';
        $this->title = 'WeChat Pay';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo WeChat Pay';
        $this->setIcon('svg/wechatpay.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
}
