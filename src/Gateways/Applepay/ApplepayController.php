<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Exception;
use WC_Coupon;

class ApplepayController
{
    public static function getShopInformation()
    {
        $country_code = preg_replace('/\:\*/', '', get_option('woocommerce_default_country'));

        wp_send_json(
            [
                'store_name'    => get_option('blogname'),
                'country_code'  => $country_code,
                'currency_code' => get_option('woocommerce_currency'),
                'culture_code'  => $country_code,
                'merchant_id'   => self::safe_get_setting('merchant_guid'),
            ]
        );
    }

    public static function getItemsFromDetailPage()
    {
        $items = self::createTemporaryCart(
            function () {
                global $woocommerce;
                $cart = $woocommerce->cart;
                return self::getCartItemsForApplePay($cart);
            }
        );

        wp_send_json(array_values($items));
    }

    /**
     * Some methods need a temporary cart if a user is on the product detail page.
     * We empty the cart and put the current shown product + quantity in the cart and reapply the coupons
     * to determine the discounts, free shipping and other rules based on that cart.
     *
     * @param callable $callback
     * @return array
     * @throws Exception
     */
    private static function createTemporaryCart($callback)
    {
        // Validate input params (throw early)
        if (! (isset($_GET['product_id']) && is_numeric($_GET['product_id']))) {
            throw new Exception('Invalid product_id');
        }

        $product_id   = absint($_GET['product_id']);
        $variation_id = isset($_GET['variation_id']) && is_numeric($_GET['variation_id']) ? absint($_GET['variation_id']) : 0;
        $quantity     = (isset($_GET['quantity']) && is_numeric($_GET['quantity']) && $_GET['quantity'] > 0) ? (int) $_GET['quantity'] : 0;

        if (! $quantity) {
            throw new Exception('Invalid quantity');
        }

        global $woocommerce;

        /** @var \WC_Cart $cart */
        $cart = $woocommerce->cart;
        if (! $cart) {
            throw new Exception('Cart not available');
        }

        // store original cart contents + coupons
        $original_cart_products = array_map(
            function ($product) {
                return [
                    'product_id'   => isset($product['product_id']) ? $product['product_id'] : 0,
                    'variation_id' => isset($product['variation_id']) ? $product['variation_id'] : 0,
                    'quantity'     => isset($product['quantity']) ? $product['quantity'] : 0,
                ];
            },
            $cart->get_cart_contents()
        );

        $original_applied_coupons = array_map(
            function ($coupon) {
                return [
                    'coupon_id' => $coupon->get_id(),
                    'code'      => $coupon->get_code(),
                ];
            },
            $cart->get_coupons()
        );

        // We'll attempt to restore the cart in all cases
        try {
            // empty current cart
            $cart->empty_cart();

            // Decide what to add: prefer a valid variation if provided, else product
            $to_add_product_id = $product_id;
            $to_add_variation_id = 0;

            if ($variation_id > 0) {
                $variation_obj = wc_get_product($variation_id);
                // Validate that it is a product and it's a valid variation for this parent product
                if ($variation_obj instanceof \WC_Product && $variation_obj->is_type('variation')) {
                    // Confirm parent id matches the provided product id (defensive)
                    $parent_id = (int) $variation_obj->get_parent_id();
                    if ($parent_id === $product_id) {
                        $to_add_variation_id = $variation_id;
                        $to_add_product_id = $product_id;
                    } else {
                        // ignore invalid variation id (it doesn't belong to this parent)
                        $to_add_variation_id = 0;
                    }
                } else {
                    // invalid variation id -> ignore
                    $to_add_variation_id = 0;
                }
            }

            // If there's no valid variation but product is variable, try to get default variation.
            $product_obj = wc_get_product($to_add_product_id);
            if ($product_obj instanceof \WC_Product && $product_obj->is_type('variable') && $to_add_variation_id === 0) {
                $default_attributes = $product_obj->get_default_attributes();
                $available_variations = $product_obj->get_children(); // variation ids

                // Try to find a default variation that exists
                foreach ($available_variations as $candidate_variation_id) {
                    $candidate = wc_get_product($candidate_variation_id);
                    if (! $candidate instanceof \WC_Product) {
                        continue;
                    }

                    // If no default attributes configured, pick the first valid variation
                    $to_add_variation_id = $candidate_variation_id;
                    break;
                }
            }

            // Add to cart (with variation only if valid)
            if ($to_add_variation_id > 0) {
                // add_to_cart: (product_id, quantity, variation_id, variation, cart_item_data)
                $cart->add_to_cart($to_add_product_id, $quantity, $to_add_variation_id);
            } else {
                // simple / variable without valid variation -> add parent product (WC will do checks)
                $cart->add_to_cart($to_add_product_id, $quantity);
            }

            // re-apply coupons
            foreach ($original_applied_coupons as $original_applied_coupon) {
                if (! empty($original_applied_coupon['code'])) {
                    $cart->apply_coupon($original_applied_coupon['code']);
                }
            }

            // allow other plugins to act
            do_action('woocommerce_before_calculate_totals', $cart);

            // recalc fees safely
            self::calculate_fee($cart);

            // calculate totals
            $cart->calculate_totals();

            do_action('woocommerce_after_calculate_totals', $cart);

            // run callback to build temporary result
            $temporary_cart_result = call_user_func($callback);

        } finally {
            // ALWAYS restore previous cart state (even if callback threw)
            $cart->empty_cart();

            // restore products
            foreach ($original_cart_products as $original_product) {
                // validate data
                $pid = isset($original_product['product_id']) ? absint($original_product['product_id']) : 0;
                $vid = isset($original_product['variation_id']) ? absint($original_product['variation_id']) : 0;
                $qty = isset($original_product['quantity']) ? absint($original_product['quantity']) : 0;

                if (! $pid || ! $qty) {
                    continue;
                }

                if ($vid > 0) {
                    // only add variation if valid
                    $variation_obj = wc_get_product($vid);
                    if ($variation_obj instanceof \WC_Product && $variation_obj->is_type('variation')) {
                        $cart->add_to_cart($pid, $qty, $vid);
                        continue;
                    }
                }

                // fallback add parent product
                $cart->add_to_cart($pid, $qty);
            }

            // restore coupons
            foreach ($original_applied_coupons as $original_applied_coupon) {
                if (! empty($original_applied_coupon['code'])) {
                    $cart->apply_coupon($original_applied_coupon['code']);
                }
            }

            // clear notices to avoid leaking temporary notices
            wc_clear_notices();
        }

        // if callback threw an exception, rethrow it now (so caller can handle)
        if (isset($temporary_cart_result)) {
            return $temporary_cart_result;
        }

        throw new Exception('Failed to build temporary cart result');
    }

    public static function calculate_fee($cart)
    {
        if (! $cart) {
            return;
        }

        // ensure session chosen payment method is set safely
        if (function_exists('WC')) {
            $wc = WC();
            if ($wc && isset($wc->session)) {
                $wc->session->set('chosen_payment_method', 'buckaroo_applepay');
            }
        }

        // recalc totals in-case not already
        if (method_exists($cart, 'calculate_totals')) {
            $cart->calculate_totals();
        }

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
        $settings = get_option('woocommerce_buckaroo_applepay_settings');

        return [
            'extrachargeamount' => isset($settings['extrachargeamount']) ? $settings['extrachargeamount'] : 0,
            'feetax'            => isset($settings['feetax']) ? $settings['feetax'] : '',
        ];
    }

    private static function getProductsFromCart($cart)
    {
        $products = [];

        foreach ($cart->get_cart_contents() as $cart_item) {
            // Defensive checks
            $variation_id = isset($cart_item['variation_id']) ? absint($cart_item['variation_id']) : 0;
            $product_id   = isset($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;

            $id = $variation_id !== 0 ? $variation_id : $product_id;
            if (! $id) {
                continue;
            }

            $product_obj = wc_get_product($id);

            // If product not found, try parent product (for variations)
            if (! ($product_obj instanceof \WC_Product)) {
                // if variation id was used, try product id
                if ($variation_id !== 0) {
                    $product_obj = wc_get_product($product_id);
                }
            }

            if (! ($product_obj instanceof \WC_Product)) {
                // skip unknown product
                continue;
            }

            $line_total = isset($cart_item['line_total']) ? $cart_item['line_total'] : 0;
            $line_tax   = isset($cart_item['line_tax']) ? $cart_item['line_tax'] : 0;
            $quantity   = isset($cart_item['quantity']) ? $cart_item['quantity'] : 0;

            $products[] = [
                'type'       => 'product',
                'id'         => absint($product_obj->get_id()),
                'name'       => $product_obj->get_name(),
                'price'      => $line_total + $line_tax,
                'quantity'   => $quantity,
                'attributes' => [],
            ];
        }

        return $products;
    }

    public static function getItemsFromCart()
    {
        global $woocommerce;

        $cart = $woocommerce->cart;
        if (! $cart) {
            wp_send_json([]);
            return;
        }

        self::calculate_fee($cart);

        $items = self::getCartItemsForApplePay($cart);

        wp_send_json(array_values($items));
    }

    private static function getCartItemsForApplePay($cart)
    {
        $items = [];

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_data = isset($cart_item['data']) ? $cart_item['data'] : null;
            $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 0;

            // If stored product object is not valid, attempt to resolve via ids
            if (! ($product_data instanceof \WC_Product)) {
                $id = 0;
                if (isset($cart_item['variation_id']) && absint($cart_item['variation_id']) > 0) {
                    $id = absint($cart_item['variation_id']);
                } elseif (isset($cart_item['product_id']) && absint($cart_item['product_id']) > 0) {
                    $id = absint($cart_item['product_id']);
                }

                if ($id > 0) {
                    $product_data = wc_get_product($id);
                }
            }

            if (! ($product_data instanceof \WC_Product)) {
                // skip invalid product item
                continue;
            }

            $line_total = isset($cart_item['line_total']) ? $cart_item['line_total'] : 0;
            $line_tax   = isset($cart_item['line_tax']) ? $cart_item['line_tax'] : 0;

            $items[] = [
                'type' => 'product',
                'id' => $product_data->get_id(),
                'name' => $product_data->get_name(),
                'price' => $line_total + $line_tax,
                'quantity' => $quantity,
                'attributes' => [],
            ];
        }

        // coupons
        foreach ($cart->get_applied_coupons() as $coupon_code) {
            // ensure coupon exists
            try {
                $coupon = new WC_Coupon($coupon_code);
            } catch (\Exception $e) {
                continue;
            }

            $discount_amount = 0;
            if (method_exists($cart, 'get_coupon_discount_amount')) {
                $discount_amount = $cart->get_coupon_discount_amount($coupon_code, false);
            }

            if ($discount_amount > 0) {
                $items[] = [
                    'type' => 'coupon',
                    'id' => $coupon->get_id(),
                    'name' => "Coupon: {$coupon_code}",
                    'price' => "-" . $discount_amount,
                    'quantity' => 1,
                    'attributes' => [],
                ];
            }
        }

        // fees
        foreach ($cart->get_fees() as $fee) {
            // fee might be WC_Order_Item_Fee-like obj or array
            $fee_total = isset($fee->amount) ? $fee->amount : 0;
            if (isset($fee->taxable) && $fee->taxable && isset($fee->tax)) {
                $fee_total += $fee->tax;
            }

            $items[] = [
                'type' => 'fee',
                'id' => isset($fee->id) ? $fee->id : 0,
                'name' => isset($fee->name) ? $fee->name : '',
                'price' => $fee_total,
                'quantity' => 1,
                'attributes' => [
                    'taxable' => isset($fee->taxable) ? $fee->taxable : false,
                ],
            ];
        }

        return $items;
    }

    public static function getShippingMethods()
    {
        // small helper to fetch methods for current cart & customer location
        $wcMethods = function () {
            global $woocommerce;

            $cart = $woocommerce->cart;
            if (! $cart) {
                return [];
            }

            $country_code = '';
            if (isset($_GET['country_code']) && is_string($_GET['country_code'])) {
                $country_code = strtoupper(sanitize_text_field($_GET['country_code']));
            }

            // Defensive: ensure customer exists
            $customer = isset($woocommerce->customer) ? $woocommerce->customer : null;
            if ($customer && method_exists($customer, 'set_shipping_country')) {
                $customer->set_shipping_country($country_code);
            }

            $packages = $woocommerce->cart->get_shipping_packages();
            if (! is_array($packages) || empty($packages)) {
                return [];
            }

            $rates = $woocommerce->shipping->calculate_shipping_for_package(current($packages));
            return isset($rates['rates']) ? $rates['rates'] : [];
        };

        if (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
            $wc_methods = self::createTemporaryCart(function () use ($wcMethods) {
                return $wcMethods();
            });
        } else {
            $wc_methods = $wcMethods();
        }

        $shipping_methods = array_map(
            function ($wc_method) {
                return [
                    'identifier' => method_exists($wc_method, 'get_id') ? $wc_method->get_id() : '',
                    'detail' => '',
                    'label' => method_exists($wc_method, 'get_label') ? $wc_method->get_label() : '',
                    'amount' => (float) number_format(
                        (method_exists($wc_method, 'get_cost') ? $wc_method->get_cost() : 0) +
                        (method_exists($wc_method, 'get_shipping_tax') ? $wc_method->get_shipping_tax() : 0),
                        2
                    ),
                ];
            },
            is_array($wc_methods) ? $wc_methods : []
        );

        wp_send_json(array_values($shipping_methods));
    }

    /**
     * Helper: read setting safely
     */
    private static function safe_get_setting($key)
    {
        $settings = get_option('woocommerce_buckaroo_applepay_settings');
        if (! is_array($settings)) {
            return '';
        }
        return isset($settings[$key]) ? $settings[$key] : '';
    }
}
