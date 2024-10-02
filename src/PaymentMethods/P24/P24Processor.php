<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\P24;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class P24Processor extends PaymentProcessorHandler
{
    protected function get_method_body(): array
    {
        return [
            'email' => $this->get_address('billing', 'email'),
            'customer' => [
                'firstName' => $this->get_address('billing', 'first_name'),
                'lastName' => $this->get_address('billing', 'last_name'),
            ]
        ];
    }
}