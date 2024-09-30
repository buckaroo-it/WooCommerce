<?php

namespace WC_Buckaroo\WooCommerce\Payment\Methods;
class Buckaroo_Trustly extends Buckaroo_Default_Method
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
