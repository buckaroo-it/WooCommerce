<?php

namespace Buckaroo\Woocommerce\Gateways\Alipay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class AlipayGateway extends AbstractPaymentGateway
{
    protected array $supportedCountries = ['CN'];

    public function __construct()
    {
        $this->id = 'buckaroo_alipay';
        $this->title = 'Alipay';
        $this->method_description = __("One of the world's largest mobile wallets, widely used by shoppers in China.", 'wc-buckaroo-bpe-gateway');
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Alipay';
        $this->setIcon('svg/alipay.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
}
