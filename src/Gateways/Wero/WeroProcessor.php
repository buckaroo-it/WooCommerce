<?php

namespace Buckaroo\Woocommerce\Gateways\Wero;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

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
            if (get_post_meta($this->get_order()->get_id(), '_wc_order_authorized', true) === 'yes') {
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
