<?php

namespace Buckaroo\Woocommerce\Gateways\Express;

use Buckaroo\Woocommerce\Gateways\ExpressProductCart;
use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;
use Buckaroo\Woocommerce\Hooks\DisableGateways;
use Buckaroo\Woocommerce\Services\Logger;
use Exception;
use RuntimeException;
use Throwable;
use WC_Order;

/**
 * The shared handling of the Apple Pay and Google Pay express routes, which
 * create the order themselves, outside the checkout: the request gate, the
 * rules of the available-gateways filter, and creating, discarding and
 * failing the express order.
 */
trait ExpressAvailability
{
    /**
     * Gate of the create-transaction route, checked before any order work
     * (BTI-1874): a POST with this wallet's nonce, for a wallet that shows an
     * express button, within the rate limit. Requests that fail the method or
     * nonce check are not counted, so a forged request cannot use up a
     * shopper's limit.
     *
     * @return string|null The message to reject the request with, or null.
     */
    protected function expressRequestError(): ?string
    {
        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : '';
        if ($method !== 'POST' || ! check_ajax_referer(static::CREATE_TRANSACTION_NONCE, 'security', false)) {
            return __('Your session has expired. Please reload the page and try again.', 'wc-buckaroo-bpe-gateway');
        }

        if (ExpressPlacements::forGateway($this->id) === []) {
            return $this->expressUnavailableMessage();
        }

        if (! ExpressRateLimit::allow()) {
            return __('Too many payment attempts. Please wait a few minutes and try again.', 'wc-buckaroo-bpe-gateway');
        }

        return null;
    }

    /**
     * A new order owned by the logged-in shopper, so per-user coupon limits
     * count it and it shows in My Account (BTI-1874).
     */
    protected function newExpressOrder(): WC_Order
    {
        $order = wc_create_order(['customer_id' => apply_filters('woocommerce_checkout_customer_id', get_current_user_id())]);
        if (! $order instanceof WC_Order) {
            throw new RuntimeException('WooCommerce could not create the express order.');
        }

        return $order;
    }

    /**
     * Reserve the order's stock until it is paid, as the checkout does, so a
     * second order cannot buy the same last units while this one waits for
     * its payment push. WooCommerce releases it when the order is paid,
     * fails or is cancelled (BTI-1874). Call once the order is complete.
     *
     * @throws ExpressCheckoutException When there is not enough stock left.
     */
    protected function reserveExpressStock(WC_Order $order): void
    {
        try {
            wc_reserve_stock_for_order($order);
        } catch (Exception $e) {
            throw new ExpressCheckoutException(wp_kses_post($e->getMessage()));
        }
    }

    /**
     * Delete an order rejected while it was being built, with the coupons and
     * stock it held, so they are free for the shopper's next attempt.
     */
    protected function discardExpressOrder(WC_Order $order): void
    {
        $order->get_data_store()->release_held_coupons($order);
        wc_release_stock_for_order($order);
        $order->delete(true);
    }

    /** The order phone number from the wallet contacts: billing, otherwise shipping. */
    protected static function walletPhone(array $billingContact, array $shippingContact): string
    {
        foreach ([$billingContact, $shippingContact] as $contact) {
            if (! empty($contact['phoneNumber']) && is_string($contact['phoneNumber'])) {
                return $contact['phoneNumber'];
            }
        }

        return '';
    }

    /** The order e-mail from the wallet contacts: billing, otherwise shipping. */
    protected static function walletEmail(array $billingContact, array $shippingContact): string
    {
        foreach ([$billingContact, $shippingContact] as $contact) {
            if (! empty($contact['emailAddress']) && is_string($contact['emailAddress'])) {
                return $contact['emailAddress'];
            }
        }

        return '';
    }

    /** Shown when the payment fails for a reason the shopper cannot act on. */
    protected function expressFailureMessage(): string
    {
        return __('Something went wrong while processing your payment. Please try again.', 'wc-buckaroo-bpe-gateway');
    }

    /**
     * Whether the wallet may be used for the express items: enabled, store
     * currency and credentials, iDIN verification of the products being
     * bought, and the available-gateways filters for the express cart, so the
     * rules of other plugins apply as well (BTI-1874). The amount rules are
     * checked again on the built order with isAllowedForOrder().
     *
     * @param  array  $customerLocation  Wallet location, as built by walletLocation().
     */
    protected function isExpressCheckoutAllowed(array $items, array $customerLocation): bool
    {
        if (! $this->isVisibleInCheckout()) {
            return false;
        }

        try {
            // Inside the callback WC()->cart is the express cart.
            return (bool) ExpressProductCart::calculateItems(
                $items,
                $this->id,
                function () {
                    return IdinProcessor::checkCurrentUserIsVerified() && $this->isListedForExpressCart();
                },
                $customerLocation
            );
        } catch (Throwable $th) {
            Logger::log(__METHOD__ . '|fail|', $th->getMessage());

            return false;
        }
    }

    /**
     * The location of the wallet contacts, in the form ExpressProductCart
     * calculates the express cart for.
     */
    protected static function walletLocation(array $billingContact, array $shippingContact): array
    {
        $location = static function (array $contact): array {
            return [
                'country' => $contact['countryCode'] ?? '',
                'state' => $contact['administrativeArea'] ?? '',
                'postcode' => $contact['postalCode'] ?? '',
                'city' => $contact['locality'] ?? '',
            ];
        };

        return ['billing' => $location($billingContact), 'shipping' => $location($shippingContact)];
    }

    /**
     * Mark the order of a payment that could not be started as failed. That
     * returns its coupon holds and recorded coupon usage, so the shopper's
     * next attempt can use the coupon again (BTI-1874).
     *
     * @param  mixed  $orderResult  The result of createOrder().
     */
    protected function failExpressOrder($orderResult, string $note): void
    {
        $order = ! empty($orderResult['data']['id']) ? wc_get_order($orderResult['data']['id']) : null;
        if ($order instanceof WC_Order) {
            $order->update_status('failed', $note);
        }
    }

    /**
     * This instance takes the payment and is enabled, so only the
     * available-gateways filters decide. The wallet is not removed for being
     * off as a checkout payment method.
     */
    private function isListedForExpressCart(): bool
    {
        $available = DisableGateways::forExpressWallet(
            $this->id,
            function (): array {
                return (array) apply_filters('woocommerce_available_payment_gateways', [$this->id => $this]);
            }
        );

        return isset($available[$this->id]);
    }

    protected function expressUnavailableMessage(): string
    {
        return __('This payment method is not available for this order. Please choose another payment method.', 'wc-buckaroo-bpe-gateway');
    }
}
