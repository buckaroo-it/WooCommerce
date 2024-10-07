<?php

namespace Buckaroo\Woocommerce\Gateways\Idin;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class IdinGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = IdinProcessor::class;
    public $issuer;

    public function getServiceCode(): string
    {
        return 'idin';
    }


    public function getMode()
    {
        return (get_option('woocommerce_buckaroo_mastersettings_settings')['useidin'] ?? false) == 'live' ? 'live' : 'test';
    }
}