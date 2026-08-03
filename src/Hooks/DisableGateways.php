<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;

/**
 * Remove gateways based on min/max value or idin verification
 */
class DisableGateways
{
    public function __construct()
    {
        add_filter('woocommerce_available_payment_gateways', [$this, 'handle']);
    }

    public function handle($available_gateways)
    {
        if (! IdinProcessor::checkCurrentUserIsVerified()) {
            return [];
        }

        if ($available_gateways) {
            if (! empty(WC()->cart)) {
                $totalCartAmount = WC()->cart->get_total(null);

                /* skip check when card total is 0 */
                if ($totalCartAmount == 0) {
                    return $available_gateways;
                }

                foreach ($available_gateways as $key => $gateway) {
                    if (! $this->isBuckarooPayment($key)) {
                        continue;
                    }

                    if (method_exists($gateway, 'isVisibleInCheckout') && ! $gateway->isVisibleInCheckout()) {
                        unset($available_gateways[$key]);
                    }

                    if (method_exists($gateway, 'isAvailable') && ! $gateway->isAvailable($totalCartAmount)) {
                        unset($available_gateways[$key]);
                    }

                    if (! empty($gateway->minvalue) || ! empty($gateway->maxvalue)) {
                        if (! empty($gateway->maxvalue) && $totalCartAmount > $gateway->maxvalue) {
                            unset($available_gateways[$key]);
                        }

                        if (! empty($gateway->minvalue) && $totalCartAmount < $gateway->minvalue) {
                            unset($available_gateways[$key]);
                        }
                    }
                }
            }
        }

        // Apple Pay and Google Pay are Express Checkout buttons by default. When
        // the merchant enables "<wallet> as checkout payment method" the wallet
        // should additionally remain a selectable gateway in the checkout;
        // otherwise it is removed here (its only frontend is the express button).
        foreach (['buckaroo_applepay', 'buckaroo_googlepay'] as $walletId) {
            if (! isset($available_gateways[$walletId])) {
                continue;
            }

            $walletGateway = $available_gateways[$walletId];
            $showAsCheckoutMethod = method_exists($walletGateway, 'isCheckoutMethodEnabled')
                && $walletGateway->isCheckoutMethodEnabled();

            if (! $showAsCheckoutMethod) {
                unset($available_gateways[$walletId]);
            }
        }
        if (isset($available_gateways['buckaroo_payperemail']) && $available_gateways['buckaroo_payperemail']->frontendVisible === 'no') {
            unset($available_gateways['buckaroo_payperemail']);
        }

        return $available_gateways;
    }

    /**
    Check if payment gateway is ours

    @param string $name

    @return boolean
     */
    protected function isBuckarooPayment(string $name)
    {
        return substr($name, 0, 8) === 'buckaroo';
    }
}
