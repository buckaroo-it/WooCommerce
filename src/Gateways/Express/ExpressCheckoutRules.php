<?php

namespace Buckaroo\Woocommerce\Gateways\Express;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\ExpressProductCart;
use Exception;
use WC_Cart;
use WC_Order;
use WP_Error;

/**
 * Checkout rules for express payments (BTI-1874). Apple Pay, Google Pay and
 * PayPal Express create the order outside the WooCommerce checkout, so the
 * checks the checkout runs on the cart are applied here.
 *
 * Call inside an ExpressProductCart callback: there WC()->cart, the customer
 * and the session are isolated copies, so removed coupons and the notices the
 * checks add never reach the shopper's live cart.
 */
final class ExpressCheckoutRules
{
    /**
     * The wallet details as the fields the checkout form posts, such as
     * billing_email and shipping_postcode.
     *
     * @param  array<string, mixed>  $billing  Billing address, in WooCommerce address keys.
     * @param  array<string, mixed>  $shipping  Shipping address, in WooCommerce address keys.
     * @return array<string, string>
     */
    public static function checkoutData(array $billing, array $shipping, string $paymentMethod): array
    {
        $data = ['payment_method' => $paymentMethod, 'ship_to_different_address' => '1'];
        foreach (['billing' => $billing, 'shipping' => $shipping] as $type => $address) {
            foreach ($address as $key => $value) {
                // The checkout has no shipping e-mail field.
                if (is_scalar($value) && ! ($type === 'shipping' && $key === 'email')) {
                    $data["{$type}_{$key}"] = (string) $value;
                }
            }
        }

        return $data;
    }

    /**
     * @param  WC_Cart  $cart  The express cart.
     * @param  array<string, string>  $checkoutData  The wallet details, as built by checkoutData().
     *
     * @throws ExpressCheckoutException With the reason for the shopper when a rule fails.
     */
    public static function check(WC_Cart $cart, array $checkoutData): void
    {
        if (self::loginRequired()) {
            throw new ExpressCheckoutException(esc_html(self::loginRequiredMessage()));
        }

        // The session copy starts with the live notices; only this check counts.
        wc_clear_notices();
        $coupons = $cart->get_applied_coupons();

        // Validity and stock, which a reused live cart has not been checked for.
        $cart->check_cart_items();
        // Coupon validity, including the per-user limit of a logged-in shopper.
        $cart->check_cart_coupons();
        ExpressProductCart::withoutCallbacksOf(
            ['woocommerce_check_cart_items'],
            ExpressProductCart::LIVE_CART_CLASSES,
            static function (): void {
                do_action('woocommerce_check_cart_items');
            }
        );
        // E-mail restrictions and the per-e-mail limit of a guest, with the wallet e-mail.
        $cart->check_customer_coupons($checkoutData);
        $validationErrors = self::runCheckoutValidation($checkoutData);

        $errors = trim(implode(' ', array_merge($validationErrors->get_error_messages(), [self::takeErrorNotices()])));
        if ($errors !== '') {
            throw new ExpressCheckoutException(wp_kses_post($errors));
        }

        if ($cart->get_applied_coupons() !== $coupons) {
            throw new ExpressCheckoutException(
                esc_html__('A coupon in your cart can no longer be used. Please review your cart and try again.', 'wc-buckaroo-bpe-gateway')
            );
        }
    }

    /**
     * Whether the shop requires an account the shopper does not have. The
     * checkout can create the account; an express payment cannot, so it is
     * refused even where the checkout would offer to register.
     */
    public static function loginRequired(): bool
    {
        // WC_Checkout::is_registration_required(), without creating the checkout
        // object, which fires woocommerce_checkout_init on product and cart pages.
        return ! is_user_logged_in()
            && (bool) apply_filters(
                'woocommerce_checkout_registration_required',
                'yes' !== get_option('woocommerce_enable_guest_checkout')
            );
    }

    /**
     * Hold the applied coupons for the order until it is paid, as the checkout
     * does, so concurrent orders cannot exceed a usage limit. Call inside the
     * same callback, after the coupon lines are added.
     *
     * @throws ExpressCheckoutException When a coupon is used up by another order.
     */
    public static function holdCoupons(WC_Order $order, string $billingEmail): void
    {
        try {
            $order->hold_applied_coupons($billingEmail);
        } catch (Exception $e) {
            throw new ExpressCheckoutException(wp_kses_post($e->getMessage()));
        }
    }

    /** Error notices added since the last clear, as one message; clears them. */
    public static function takeErrorNotices(): string
    {
        $messages = [];
        foreach (wc_get_notices('error') as $notice) {
            $message = is_array($notice) ? ($notice['notice'] ?? '') : $notice;
            if (is_string($message) && $message !== '') {
                $messages[] = $message;
            }
        }
        wc_clear_notices();

        return implode(' ', $messages);
    }

    /**
     * Fire the checkout's validation hooks, so the checkout rules of other
     * plugins apply as well. Many validators read the posted checkout form
     * rather than the data they are given, so the wallet details are posted
     * while the hooks run, slashed like real request data.
     */
    private static function runCheckoutValidation(array $checkoutData): WP_Error
    {
        $errors = new WP_Error();
        // phpcs:disable WordPress.Security.NonceVerification -- The express routes verify their own nonce.
        $post = $_POST;
        $request = $_REQUEST;
        $_POST = array_merge($_POST, wp_slash($checkoutData));
        $_REQUEST = array_merge($_REQUEST, wp_slash($checkoutData));

        try {
            // The gateways' own callback resets the checkout's awaiting order, which express does not use.
            ExpressProductCart::withoutCallbacksOf(
                ['woocommerce_checkout_process'],
                [AbstractPaymentGateway::class],
                static function (): void {
                    do_action('woocommerce_checkout_process');
                }
            );
            // The live cart's own coupon check is stripped; check() ran it on the express cart.
            ExpressProductCart::withoutCallbacksOf(
                ['woocommerce_after_checkout_validation'],
                ExpressProductCart::LIVE_CART_CLASSES,
                static function () use ($checkoutData, $errors): void {
                    do_action('woocommerce_after_checkout_validation', $checkoutData, $errors);
                }
            );
        } finally {
            $_POST = $post;
            $_REQUEST = $request;
        }
        // phpcs:enable WordPress.Security.NonceVerification

        return $errors;
    }

    private static function loginRequiredMessage(): string
    {
        // The checkout's own filter, so a shop's custom wording applies here too.
        $message = apply_filters(
            'woocommerce_checkout_must_be_logged_in_message',
            __('You must be logged in to checkout.', 'wc-buckaroo-bpe-gateway')
        );

        return is_string($message) ? $message : __('You must be logged in to checkout.', 'wc-buckaroo-bpe-gateway');
    }
}
