<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class PostepayGateway extends SingleCreditCardGateway
{
    protected array $supportedCountries = ['IT'];

    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_postepay';
        $this->title = 'PostePay';
        $this->method_title = 'Buckaroo PostePay';
    }
}
