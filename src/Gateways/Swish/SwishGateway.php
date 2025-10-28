<?php

namespace Buckaroo\Woocommerce\Gateways\Swish;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class SwishGateway extends AbstractPaymentGateway
{
    public function __construct()
    {
        $this->id = 'buckaroo_swish';
        $this->title = 'Swish';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Swish';
        $this->method_description = __('Swish is Sweden\'s real-time mobile payment method, authorized via BankID.', 'wc-buckaroo-bpe-gateway');
        $this->setIcon('svg/swish.svg');
        $this->supportedCurrencies = ['SEK'];
        parent::__construct();
        $this->addRefundSupport();
    }
}
