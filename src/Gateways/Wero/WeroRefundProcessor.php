<?php

namespace Buckaroo\Woocommerce\Gateways\Wero;

use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;

class WeroRefundProcessor extends AbstractRefundProcessor
{
    /**
     * Determine whether to call Refund or CancelAuthorize for Wero.
     *
     * Supports: Refund, CancelAuthorize.
     */
    public function getAction(): string
    {
        $order = $this->getOrder();

        $isAuthorized = get_post_meta($order->get_id(), '_wc_order_authorized', true) === 'yes';
        $isCaptured = (bool) get_post_meta($order->get_id(), '_wc_order_is_captured', true);

        if ($isAuthorized && ! $isCaptured) {
            // This must match the Buckaroo SDK method name on the Wero payment method.
            return 'cancelAuthorize';
        }

        return parent::getAction();
    }
}
