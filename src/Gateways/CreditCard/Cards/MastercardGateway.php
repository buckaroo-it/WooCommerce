<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class MastercardGateway extends SingleCreditCardGateway
{
    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_mastercard';
        $this->title = 'Mastercard';
        $this->method_title = 'Buckaroo Mastercard';
    }
}
