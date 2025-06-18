<?php

namespace Buckaroo\Woocommerce\Gateways\Paypal;

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

class PaypalProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    public function getAction(): string
    {
        if ($this->isSellerProtection() && ! $this->isExpress()) {
            return 'extraInfo';
        }

        return 'pay';
    }

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        if ($this->isExpress()) {
            return [
                'payPalOrderId' => $this->getExpressId(),
                'additionalParameters' => [
                    'is_paypal_express' => true,
                    'real_order_id' => $this->get_order()->get_id(),
                ],
            ];
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
        if (method_exists($this->gateway, 'get_express_order_id')) {
            return $this->gateway->get_express_order_id();
        }

        return null;
    }

    /**
     * Check if seller protection is enabled
     */
    private function isSellerProtection(): bool
    {
        return $this->gateway->get_option('sellerprotection', 'TRUE') === 'TRUE';
    }

    /**
     * Get seller protection data
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
                'country' => $this->getAddress('billing', 'country'),
            ],
            'phone' => [
                'mobile' => $this->getAddress('billing', 'phone'),
            ],
        ];
    }

    public function beforeReturnHandler(ResponseParser $responseParser)
    {
        if ($responseParser->get('coreStatus') === BuckarooTransactionStatus::STATUS_ON_HOLD) {
            $responseParser->set('coreStatus', BuckarooTransactionStatus::STATUS_CANCELLED);
        }
    }
}
