<?php

namespace Buckaroo\Woocommerce\Gateways\Twint;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class TwintGateway extends AbstractPaymentGateway
{
    protected array $supportedCurrencies = ['CHF'];

    public function __construct()
    {
        $this->id = 'buckaroo_twint';
        $this->title = 'Twint';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Twint';
        $this->method_description = __('TWINT is Switzerland\'s payment app for convenient and secure payments using your smartphone.', 'wc-buckaroo-bpe-gateway');
        $this->setIcon('svg/twint.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
}
