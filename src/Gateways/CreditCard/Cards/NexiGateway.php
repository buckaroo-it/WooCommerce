<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class NexiGateway extends SingleCreditCardGateway
{
    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_nexi';
        $this->title = 'Nexi';
        $this->method_title = 'Buckaroo Nexi';
    }
}
