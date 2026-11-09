<?php

namespace Buckaroo\Woocommerce\Gateways\Express;

use Buckaroo\Woocommerce\Gateways\ExpressProductCart;
use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;
use Buckaroo\Woocommerce\Services\Logger;
use Throwable;

/**
 * Checkout rules for wallet express transactions. The express routes create
 * the order themselves and never pass the available-gateways filter
 * (DisableGateways), so the same rules are applied here.
 */
trait ExpressAvailability
{
    /**
     * Whether the wallet may be used for the express items: enabled, store
     * currency and credentials, and iDIN verification of the products being
     * bought. The amount rules need the order total and are checked on the
     * built order with isAllowedForOrder().
     */
    protected function isExpressCheckoutAllowed(array $items): bool
    {
        if (! $this->isVisibleInCheckout()) {
            return false;
        }

        try {
            // Inside the callback WC()->cart is the express cart.
            return (bool) ExpressProductCart::calculateItems(
                $items,
                $this->id,
                static function () {
                    return IdinProcessor::checkCurrentUserIsVerified();
                }
            );
        } catch (Throwable $th) {
            Logger::log(__METHOD__ . '|fail|', $th->getMessage());

            return false;
        }
    }

    protected function expressUnavailableMessage(): string
    {
        return __('This payment method is not available for this order. Please choose another payment method.', 'wc-buckaroo-bpe-gateway');
    }
}
