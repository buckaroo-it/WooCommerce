<?php

namespace Buckaroo\Woocommerce\Gateways\Ideal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class IdealGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = IdealProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_ideal';
        $this->title = 'iDEAL';
        $this->has_fields = true;
        $this->method_title = 'Buckaroo iDEAL';
        $this->setIcon('svg/ideal.svg');

        parent::__construct();
        $this->addRefundSupport();
        apply_filters('buckaroo_init_payment_class', $this);
    }
}
