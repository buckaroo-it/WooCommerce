<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class AmexGateway extends SingleCreditCardGateway
{
    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_amex';
        $this->title = 'Amex';
        $this->method_title = 'Buckaroo Amex';
    }
}
