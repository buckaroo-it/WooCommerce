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

        $payment = $available_gateways ? $this->resolvePayment() : null;
        if ($payment !== null) {
            [$amount, $currency] = $payment;

            foreach ($available_gateways as $key => $gateway) {
                if (! $this->isBuckarooPayment($key)) {
                    continue;
                }

                if (method_exists($gateway, 'isVisibleInCheckout') && ! $gateway->isVisibleInCheckout()) {
                    unset($available_gateways[$key]);
                    continue;
                }

                if (method_exists($gateway, 'isAllowedFor') && ! $gateway->isAllowedFor($amount, $currency)) {
                    unset($available_gateways[$key]);
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
     * Amount and currency being paid, resolved the way core's get_order_total()
     * does: the order on the order-pay page, otherwise the cart. Null when
     * neither is available (e.g. admin screens).
     *
     * @return array{0: float, 1: string}|null
     */
    protected function resolvePayment(): ?array
    {
        $orderId = absint(get_query_var('order-pay'));
        if ($orderId > 0) {
            $order = wc_get_order($orderId);

            return $order instanceof \WC_Order
                ? [(float) $order->get_total('edit'), (string) $order->get_currency('edit')]
                : null;
        }

        if (empty(WC()->cart)) {
            return null;
        }

        return [(float) WC()->cart->get_total('edit'), get_woocommerce_currency()];
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
