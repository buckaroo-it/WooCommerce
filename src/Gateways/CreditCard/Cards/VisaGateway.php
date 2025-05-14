<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class VisaGateway extends SingleCreditCardGateway
{
    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_visa';
        $this->title = 'Visa';
        $this->method_title = 'Buckaroo Visa';
    }
}
