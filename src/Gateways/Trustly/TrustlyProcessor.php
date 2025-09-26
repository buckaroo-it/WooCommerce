<?php

namespace Buckaroo\Woocommerce\Gateways\Trustly;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class TrustlyProcessor extends AbstractPaymentProcessor
{
    protected function getMethodBody(): array
    {
        return [
            'email' => $this->getAddress('billing', 'email'),
            'country' => $this->getAddress('billing', 'country'),
            'customer' => [
                'firstName' => $this->getAddress('billing', 'first_name'),
                'lastName' => $this->getAddress('billing', 'last_name'),
            ],
        ];
    }
}
