<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class CartebancaireGateway extends SingleCreditCardGateway
{
    protected array $supportedCountries = ['FR'];

    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_cartebancaire';
        $this->title = 'Carte Bancaire';
        $this->method_title = 'Buckaroo Carte Bancaire';
    }
}
