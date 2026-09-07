<?php

namespace Buckaroo\Woocommerce\Gateways\Bizum;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class BizumGateway extends AbstractPaymentGateway
{
    public function __construct()
    {
        $this->id = 'buckaroo_bizum';
        $this->title = 'Bizum';
        $this->method_description = __("Spain's mobile payment method, with instant bank-to-bank transfers via phone number.", 'wc-buckaroo-bpe-gateway');
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Bizum';
        $this->setIcon('svg/bizum.svg');

        $this->supportedCurrencies = ['EUR'];
        $this->supportedCountries = ['ES'];

        parent::__construct();
        $this->addRefundSupport();
    }
}
