<?php

class Buckaroo_P24 extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return [
            'email'    => $this->get_address('billing', 'email'),
            'customer' => [
                'firstName' => $this->get_address('billing', 'first_name'),
                'lastName'  => $this->get_address('billing', 'last_name'),
            ]
        ];
    }
}
