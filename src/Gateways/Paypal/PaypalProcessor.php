<?php

namespace Buckaroo\Woocommerce\Gateways\Paypal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class PaypalProcessor extends AbstractPaymentProcessor
{
    /** @inheritDoc */
    public function getAction(): string
    {
        if ($this->isSellerProtection() && !$this->isExpress()) {
            return 'extraInfo';
        }
        return 'pay';
    }

    /** @inheritDoc */
    protected function getMethodBody(): array
    {
        if ($this->isExpress()) {
            return ['payPalOrderId' => $this->getExpressId()];
        }

        if ($this->isSellerProtection()) {
            return $this->getSellerData();
        }
        return [];
    }

    private function isExpress(): bool
    {
        return is_string($this->getExpressId());
    }

    private function getExpressId(): ?string
    {
        if (method_exists($this->gateway, "get_express_order_id")) {
            return $this->gateway->get_express_order_id();
        }
        return null;
    }

    /**
     * Check if seller protection is enabled
     *
     * @return bool
     */
    private function isSellerProtection(): bool
    {
        return $this->gateway->get_option('sellerprotection', 'TRUE') === 'TRUE';
    }

    /**
     * Get seller protection data
     *
     * @return array
     */
    private function getSellerData(): array
    {
        return [
            'customer' => [
                'name' => $this->order_details->get_full_name(),
            ],
            'address' => [
                'street' => $this->getAddress('billing', 'address_1'),
                'zipcode' => $this->getAddress('billing', 'postcode'),
                'city' => $this->getAddress('billing', 'city'),
                'state' => $this->getAddress('billing', 'state'),
                'country' => $this->getAddress('billing', 'country')
            ],
            'phone' => [
                'mobile' => $this->getAddress('billing', 'phone')
            ]
        ];
    }
}