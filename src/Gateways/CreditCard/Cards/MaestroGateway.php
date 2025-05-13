<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class MaestroGateway extends SingleCreditCardGateway
{
    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_maestro';
        $this->title = 'Maestro';
        $this->method_title = 'Buckaroo Maestro';
    }
}
