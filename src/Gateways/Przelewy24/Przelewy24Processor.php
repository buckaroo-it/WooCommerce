<?php

namespace Buckaroo\Woocommerce\Gateways\Przelewy24;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class Przelewy24Processor extends AbstractPaymentProcessor
{
    protected function getMethodBody(): array
    {
        return [
            'email' => $this->getAddress('billing', 'email'),
            'customer' => [
                'firstName' => $this->getAddress('billing', 'first_name'),
                'lastName' => $this->getAddress('billing', 'last_name'),
            ],
        ];
    }
}
