<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class CartebleuevisaGateway extends SingleCreditCardGateway
{
    protected array $supportedCountries = ['FR'];

    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_cartebleuevisa';
        $this->title = 'Carte Bleue';
        $this->method_title = 'Buckaroo Carte Bleue';
    }
}
