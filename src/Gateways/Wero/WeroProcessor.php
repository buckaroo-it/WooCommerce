<?php

namespace Buckaroo\Woocommerce\Gateways\Wero;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;

class WeroProcessor extends AbstractPaymentProcessor
{
    /**
     * Determine which Wero action to call on the Buckaroo API.
     *
     * Supports: Pay, Authorize, Capture.
     */
    public function getAction(): string
    {
        if ($this->isAuthorizationFlowEnabled()) {
            // If the order is already authorized, subsequent calls should capture.
            if (OrderMeta::get($this->get_order(), '_wc_order_authorized') === 'yes') {
                return 'capture';
            }

            return 'authorize';
        }

        return parent::getAction();
    }

    private function isAuthorizationFlowEnabled(): bool
    {
        return $this->gateway->get_option('weropayauthorize', 'pay') === 'authorize';
    }
}
