<?php

namespace Buckaroo\Woocommerce\Gateways\Ideal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class IdealGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = IdealProcessor::class;

    protected array $supportedCountries = ['NL'];

    public function __construct()
    {
        $this->id = 'buckaroo_ideal';
        $this->title = 'iDEAL | Wero';
        $this->method_description = "The Netherlands' most-used online payment method, via the shopper's own bank, now co-branded with Wero.";
        $this->has_fields = true;
        $this->method_title = 'Buckaroo iDEAL | Wero';
        $this->setIcon('svg/ideal-wero.svg');

        parent::__construct();
        $this->addRefundSupport();
        apply_filters('buckaroo_init_payment_class', $this);
    }
}
