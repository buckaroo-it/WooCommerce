<?php

namespace Buckaroo\Woocommerce\Gateways\Kbc;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class KbcGateway extends AbstractPaymentGateway
{
    protected array $supportedCountries = ['BE'];

    public function __construct()
    {
        $this->id = 'buckaroo_kbc';
        $this->title = 'KBC';
        $this->method_description = 'Direct bank payments for customers of KBC/CBC in Belgium.';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo KBC';
        $this->setIcon('svg/kbc.svg');

        parent::__construct();
        $this->addRefundSupport();
        apply_filters('buckaroo_init_payment_class', $this);
    }
}
