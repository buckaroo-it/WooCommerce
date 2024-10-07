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
        return \BuckarooConfig::getIdinMode();
    }
}