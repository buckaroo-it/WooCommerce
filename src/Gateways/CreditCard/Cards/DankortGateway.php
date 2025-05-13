<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class DankortGateway extends SingleCreditCardGateway
{
    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_dankort';
        $this->title = 'Dankort';
        $this->method_title = 'Buckaroo Dankort';
    }
}
