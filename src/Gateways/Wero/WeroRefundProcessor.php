<?php

namespace Buckaroo\Woocommerce\Gateways\Wero;

use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;

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

        $isAuthorized = OrderMeta::get($order, '_wc_order_authorized') === 'yes';
        $isCaptured = (bool) OrderMeta::get($order, '_wc_order_is_captured');

        if ($isAuthorized && ! $isCaptured) {
            // This must match the Buckaroo SDK method name on the Wero payment method.
            return 'cancelAuthorize';
        }

        return parent::getAction();
    }
}
