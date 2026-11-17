<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Exception;

class CreditCardProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    public function getAction(): string
    {
        if ($this->isAuthorization()) {
            if ($this->isCapture()) {
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

    private function isCapture(): bool
    {
        return OrderMeta::get($this->get_order(), '_wc_order_authorized') == 'yes';
    }

    /**
     * The card method configured on the gateway decides between hosted fields
     * and the Buckaroo payment page.
     */
    private function isEncripted(): bool
    {
        return $this->gateway instanceof CreditCardGateway && $this->gateway->usesHostedFields();
    }

    /** @inherictDoc */
    protected function getMethodBody(): array
    {
        if ($this->isCapture()) {
            return [
                'name' => OrderMeta::get($this->get_order(), '_payment_method_transaction'),
            ];
        }

        $body = [
            'name' => $this->getCardIssuer(),
        ];

        if ($this->isEncripted()) {
            $sessionId = $this->gateway->getSubmittedSessionId();

            if ($sessionId === null) {
                throw new Exception(esc_html__('Please complete the card form before proceeding.', 'wc-buckaroo-bpe-gateway'));
            }

            $body['sessionId'] = $sessionId;
        }

        return $body;
    }

    /**
     * Card brand for the payment, limited to the brands allowed for the gateway.
     *
     * @throws Exception When no allowed card brand was selected.
     */
    private function getCardIssuer(): string
    {
        $issuer = $this->gateway instanceof CreditCardGateway ? $this->gateway->getSubmittedCardIssuer() : null;

        if ($issuer === null) {
            throw new Exception(esc_html__('A valid credit card is required.', 'wc-buckaroo-bpe-gateway'));
        }

        return $issuer;
    }
}
