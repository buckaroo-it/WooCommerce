<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class VisaelectronGateway extends SingleCreditCardGateway
{
    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_visaelectron';
        $this->title = 'Visa Electron';
        $this->method_title = 'Buckaroo Visa Electron';
    }
}
