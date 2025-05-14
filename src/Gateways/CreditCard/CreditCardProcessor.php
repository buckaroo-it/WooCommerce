<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class CreditCardProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    public function getAction(): string
    {
        if ($this->isAuthorization()) {
            if (get_post_meta($this->get_order()->get_id(), '_wc_order_authorized', true) == 'yes') {
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
            'name' => strtolower($this->request->input($this->gateway->id . '-creditcard-issuer')) ?: get_post_meta($this->get_order()->get_id(), '_payment_method_transaction', true),
        ];

        if ($this->isEncripted()) {
            $encryptedData = $this->request->input($this->gateway->id . '-encrypted-data') ?: get_post_meta($this->get_order()->get_id(), '_payload_encrypted_card_data', true);
            add_post_meta($this->get_order()->get_id(), '_payload_encrypted_card_data', $encryptedData, true);

            $body['sessionId'] = $encryptedData;
        }

        return $body;
    }
}
