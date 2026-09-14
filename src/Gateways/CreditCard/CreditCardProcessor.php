<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;

class CreditCardProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    public function getAction(): string
    {
        if ($this->isAuthorization()) {
            if (OrderMeta::get($this->get_order(), '_wc_order_authorized') == 'yes') {
                return 'capture';
            }

            return $this->isEncripted() ? 'authorizeWithToken' : 'authorize';
        }

        if ($this->isEncripted()) {
            return 'payWithToken';
        }

        return parent::getAction();
    }

    private function isAuthorization(): bool
    {
        return $this->gateway->get_option('creditcardpayauthorize', 'pay') === 'authorize';
    }

    private function isEncripted(): bool
    {
        return ($this->request->input($this->gateway->id . '-creditcard-issuer') ?: null) !== null &&
            ($this->request->input($this->gateway->id . '-encrypted-data') ?: null) !== null;
    }

    /** @inherictDoc */
    protected function getMethodBody(): array
    {
        $body = [
            'name' => strtolower($this->request->input($this->gateway->id . '-creditcard-issuer')) ?: OrderMeta::get($this->get_order(), '_payment_method_transaction'),
        ];

        if ($this->isEncripted()) {
            $encryptedData = $this->request->input($this->gateway->id . '-encrypted-data') ?: OrderMeta::get($this->get_order(), '_payload_encrypted_card_data');
            OrderMeta::add($this->get_order(), '_payload_encrypted_card_data', $encryptedData, true);

            $body['sessionId'] = $encryptedData;
        }

        return $body;
    }
}
