<?php

class Buckaroo_PayByBank extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return [
            'issuer' => $this->request_string('buckaroo-paybybank-issuer')
        ];
    }
}
