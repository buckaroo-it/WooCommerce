<?php

namespace Buckaroo\Woocommerce\Gateways\KnakenSettle;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class KnakenSettleGateway extends AbstractPaymentGateway
{
    protected array $supportedCountries = ['NL'];

    public function __construct()
    {
        $this->id = 'buckaroo_knaken';
        $this->title = 'goSettle';
        $this->method_description = 'Crypto payment method accepting digital assets such as Bitcoin, Ethereum and USDC.';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo goSettle';
        $this->setIcon('svg/goSettle.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
}
