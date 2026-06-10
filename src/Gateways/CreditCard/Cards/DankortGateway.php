<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class DankortGateway extends SingleCreditCardGateway
{
    protected array $supportedCountries = ['DK'];

    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_dankort';
        $this->title = 'Dankort';
        $this->method_title = 'Buckaroo Dankort';
    }
}
