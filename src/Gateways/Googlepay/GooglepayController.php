<?php

namespace Buckaroo\Woocommerce\Gateways\Googlepay;

use Exception;
use WC_Coupon;

class GooglepayController
{
    public static function getShopInformation()
    {
        $country_code = preg_replace('/\:\*/', '', get_option('woocommerce_default_country'));
        $settings = get_option('woocommerce_buckaroo_googlepay_settings', []);

        wp_send_json(
            [
                'store_name' => get_option('blogname'),
                'country_code' => $country_code,
                'currency_code' => get_option('woocommerce_currency'),
                'culture_code' => $country_code,
                'merchant_id' => $settings['merchant_guid'] ?? '',
                'google_merchant_id' => $settings['google_merchant_id'] ?? '',
                'mode' => $settings['mode'] ?? 'test',
                'button_style' => $settings['button_style'] ?? 'black',
                'locale' => substr(get_locale(), 0, 2),
            ]
        );
    }

    public static function getItemsFromDetailPage()
    {
        $items = self::createTemporaryCart(
            function () {
                global $woocommerce;

                $cart = $woocommerce->cart;

                return self::getCartItemsForGooglePay($cart);
            }
        );

        wp_send_json(array_values($items));
    }

    /**
     * Some methods need to have a temporary cart if a user is on the product detail page
     * We empty the cart and put the current shown product + quantity in the cart and reapply the coupons
     * to determine the discounts, free shipping and other rules based on that cart
     *
     * @return array
     */
    private static function createTemporaryCart($callback)
    {
        if (! (isset($_GET['product_id']) && is_numeric($_GET['product_id']))) {
            throw new Exception('Invalid product_id');
        }

        if (isset($_GET['variation_id']) && ! is_numeric($_GET['variation_id'])) {
            throw new Exception('Invalid variation_id');
        }

        if (! (isset($_GET['quantity']) && is_numeric($_GET['quantity']) && $_GET['quantity'] > 0)) {
            throw new Exception('Invalid quantity');
        }

        global $woocommerce;

        /** @var WC_Cart */
        $cart = $woocommerce->cart;

        $current_shown_product = [
            'product_id' => absint($_GET['product_id']),
            'variation_id' => absint($_GET['variation_id']),
            'quantity' => (int) $_GET['quantity'],
        ];

        $original_cart_products = array_map(
            function ($product) {
                return [
                    'product_id' => $product['product_id'],
                    'variation_id' => $product['variation_id'],
                    'quantity' => $product['quantity'],
                ];
            },
            $cart->get_cart_contents()
        );

        $original_applied_coupons = array_map(
            function ($coupon) {
                return [
                    'coupon_id' => $coupon->get_id(),
                    'code' => $coupon->get_code(),
                ];
            },
            $cart->get_coupons()
        );

        $cart->empty_cart();

        if ($current_shown_product['product_id'] != $current_shown_product['variation_id']) {
            $cart->add_to_cart(
                $current_shown_product['product_id'],
                $current_shown_product['quantity'],
                $current_shown_product['variation_id']
            );
        } else {
            $cart->add_to_cart(
                $current_shown_product['product_id'],
                $current_shown_product['quantity'],
            );
        }

        foreach ($original_applied_coupons as $original_applied_coupon) {
            $cart->apply_coupon($original_applied_coupon['code']);
        }

        do_action('woocommerce_before_calculate_totals', $cart);

        self::calculate_fee($cart);

        $cart->calculate_totals();

        do_action('woocommerce_after_calculate_totals', $cart);

        $temporary_cart_result = call_user_func($callback);

        // restore previous cart
        $cart->empty_cart();

        foreach ($original_cart_products as $original_product) {
            $cart->add_to_cart(
                $original_product['product_id'],
                $original_product['quantity'],
                $original_product['variation_id']
            );
        }

        foreach ($original_applied_coupons as $original_applied_coupon) {
            $cart->apply_coupon($original_applied_coupon['code']);
        }

        wc_clear_notices();

        return $temporary_cart_result;
    }

    public static function calculate_fee($cart)
    {
        WC()->session->set('chosen_payment_method', 'buckaroo_googlepay');
        $cart->calculate_totals();

        $feed_settings = self::get_extra_feed_settings();
        do_action(
            'buckaroo_cart_calculate_fees',
            $cart,
            $feed_settings['extrachargeamount'],
            $feed_settings['feetax']
        );
    }

    private static function get_extra_feed_settings()
    {
        $settings = get_option('woocommerce_buckaroo_googlepay_settings');

        return [
            'extrachargeamount' => isset($settings['extrachargeamount']) ? $settings['extrachargeamount'] : 0,
            'feetax' => isset($settings['feetax']) ? $settings['feetax'] : '',
        ];
    }

    public static function getItemsFromCart()
    {
        global $woocommerce;

        $cart = $woocommerce->cart;

        self::calculate_fee($cart);

        $items = self::getCartItemsForGooglePay($cart);

        wp_send_json(array_values($items));
    }

    private static function getCartItemsForGooglePay($cart)
    {
        $items = [];

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];

            $line_total = $cart_item['line_total'] + $cart_item['line_tax'];

            $items[] = [
                'type' => 'product',
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $line_total,
                'quantity' => $quantity,
                'attributes' => [],
            ];
        }

        foreach ($cart->get_applied_coupons() as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);

            $discount_amount = $cart->get_coupon_discount_amount($coupon_code, false);

            if ($discount_amount > 0) {
                $items[] = [
                    'type' => 'coupon',
                    'id' => $coupon->get_id(),
                    'name' => "Coupon: {$coupon_code}",
                    'price' => "-{$discount_amount}",
                    'quantity' => 1,
                    'attributes' => [],
                ];
            }
        }

        foreach ($cart->get_fees() as $fee) {
            $fee_total = $fee->amount;
            if ($fee->taxable && isset($fee->tax)) {
                $fee_total += $fee->tax;
            }

            $items[] = [
                'type' => 'fee',
                'id' => $fee->id,
                'name' => $fee->name,
                'price' => $fee_total,
                'quantity' => 1,
                'attributes' => [
                    'taxable' => $fee->taxable,
                ],
            ];
        }

        return $items;
    }

    public static function getShippingMethods()
    {
        $wcGooglepayMethods = function () {
            global $woocommerce;

            $cart = $woocommerce->cart;

            $country_code = '';
            if (isset($_GET['country_code']) && is_string($_GET['country_code'])) {
                $country_code = strtoupper(sanitize_text_field($_GET['country_code']));
            }

            $customer = $woocommerce->customer;
            $customer->set_shipping_country($country_code);

            $packages = $woocommerce->cart->get_shipping_packages();

            return $woocommerce->shipping
                ->calculate_shipping_for_package(current($packages))['rates'];
        };

        if (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
            $wc_methods = self::createTemporaryCart(
                function () use ($wcGooglepayMethods) {
                    return $wcGooglepayMethods();
                }
            );
        } else {
            $wc_methods = $wcGooglepayMethods();
        }

        $shipping_methods = array_map(
            function ($wc_method) {
                return [
                    'identifier' => $wc_method->get_id(),
                    'detail' => '',
                    'label' => $wc_method->get_label(),
                    'amount' => (float) number_format($wc_method->get_cost() + $wc_method->get_shipping_tax(), 2),
                ];
            },
            $wc_methods
        );

        wp_send_json(array_values($shipping_methods));
    }
}
