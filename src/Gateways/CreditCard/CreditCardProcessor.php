<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class CreditCardProcessor extends AbstractPaymentProcessor
{

    /** @inheritDoc */
    public function getAction(): string
    {
        if ($this->gateway->get_option('creditcardpayauthorize') == 'authorize') {
            return 'authorizeEncrypted';
        }

        if ($this->isEncripted()) {
            return 'payEncrypted';
        }
        return parent::getAction();
    }

    private function isEncripted(): bool
    {
        return
            $this->request->input('creditcard-issuer') !== null &&
            $this->request->input('encrypted-data') !== null;
    }

    /** @inheritDoc */
    protected function getMethodBody(): array
    {
        $body = [
            'name' => $this->request->input('creditcard-issuer', ''),
        ];

        if ($this->isEncripted()) {
            $body = array_merge(
                $body,
                [
                    'encryptedCardData' => $this->request->input('encrypted-data')
                ]
            );
        }

        return $body;
    }

    protected function request(string $key, $default = '')
    {
        return parent::request($this->gateway->id . "-" . $key);
    }
}