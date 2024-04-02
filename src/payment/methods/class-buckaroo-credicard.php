<?php

class Buckaroo_CreditCard extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        $body = [
            'name' => $this->request_string('creditcard-issuer', ''),
        ];

        if ($this->is_encripted()) {
            $body = array_merge(
                $body,
                [
                    'encryptedCardData' =>  $this->request_string('encrypted-data')
                ]
            );
        }
        return $body;
    }

    /** @inheritDoc */
    public function get_action(): string
    {
        if ($this->is_encripted()) {
            return 'payEncrypted';
        }
        return parent::get_action();
    }

    protected function request(string $key, $default = '')
    {
        return parent::request($this->gateway->id . "-" . $key);
    }

    private function is_encripted(): bool
    {
        return
            $this->request_string('creditcard-issuer') !== null &&
            $this->request_string('encrypted-data') !== null;
    }
}
