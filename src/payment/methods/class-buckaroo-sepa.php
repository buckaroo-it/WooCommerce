<?php

class Buckaroo_Sepa extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        if (
            $this->request_string('buckaroo-sepadirectdebit-accountname') !== null &&
            $this->request_string('buckaroo-sepadirectdebit-iban') !== null
        ) {
            return [
                'iban'              => $this->request_string('buckaroo-sepadirectdebit-iban'),
                'customer'      => [
                    'name'          => $this->request_string('buckaroo-sepadirectdebit-accountname')
                ]
            ];
        }
        return [];
    }
}
