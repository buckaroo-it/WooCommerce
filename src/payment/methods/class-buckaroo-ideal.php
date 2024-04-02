<?php

class Buckaroo_Ideal extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        if (!$this->show_issuers()) {
            return [
                'continueOnIncomplete' => true
            ];
        }
        return [
            'issuer' => $this->request_string('buckaroo-ideal-issuer')
        ];
    }

    private function show_issuers(): bool
    {
        return $this->gateway->get_option('show_issuers') !== 'no';
    }
}
