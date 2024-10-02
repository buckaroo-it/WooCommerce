<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\Trustly;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class TrustlyProcessor extends PaymentProcessorHandler
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return [
            'country' => $this->get_address('billing', 'country'),
            'customer' => [
                'firstName' => $this->get_address('billing', 'first_name'),
                'lastName' => $this->get_address('billing', 'last_name')
            ]
        ];
    }
}