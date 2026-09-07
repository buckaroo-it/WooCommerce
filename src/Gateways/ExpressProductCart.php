<?php

namespace Buckaroo\Woocommerce\Gateways;

use Exception;
use UnexpectedValueException;
use WC_Product_Attribute;

final class ExpressProductCart
{
    public static function calculate(array $request, string $payment_method, callable $callback)
    {
        return self::withProducts(
            [self::validatedProduct($request)],
            $payment_method,
            $callback,
            false,
            self::customerLocation($request)
        );
    }

    public static function calculateItems(
        array $items,
        string $payment_method,
        callable $callback,
        array $customer_location = []
    ) {
        return self::withProducts(
            self::validatedItems($items),
            $payment_method,
            $callback,
            true,
            self::customerLocation($customer_location)
        );
    }

    public static function calculateCurrent(
        string $payment_method,
        callable $callback,
        array $customer_location = []
    ) {
        global $woocommerce;

        if (! $woocommerce || ! $woocommerce->cart) {
            throw new UnexpectedValueException('WooCommerce cart is unavailable.');
        }

        return self::withProducts(
            self::productsFromCart($woocommerce->cart),
            $payment_method,
            $callback,
            true,
            self::customerLocation($customer_location)
        );
    }

    private static function withProducts(
        array $products,
        string $payment_method,
        callable $callback,
        bool $preserve_matching_cart,
        array $customer_location
    ) {
        global $woocommerce;

        if (! $woocommerce || ! $woocommerce->cart) {
            throw new UnexpectedValueException('WooCommerce cart is unavailable.');
        }

        $live_cart = $woocommerce->cart;
        $live_session = $woocommerce->session;
        $live_customer = $woocommerce->customer;
        $shipping = WC()->shipping();
        $shipping_packages = $shipping ? $shipping->packages : null;

        $use_matching_cart = $preserve_matching_cart && self::cartCoversProducts($live_cart, $products);
        $temporary_cart = clone $live_cart;
        if ($use_matching_cart) {
            $temporary_cart->set_cart_contents(self::detachedValue($temporary_cart->get_cart_contents()));
            $temporary_cart->fees_api()->set_fees(self::detachedValue($temporary_cart->get_fees()));
        } else {
            $temporary_cart->set_cart_contents([]);
            $temporary_cart->set_removed_cart_contents([]);
            $temporary_cart->set_applied_coupons($live_cart->get_applied_coupons());
            $temporary_cart->set_coupon_discount_totals([]);
            $temporary_cart->set_coupon_discount_tax_totals([]);
            $temporary_cart->set_totals([]);
            $temporary_cart->fees_api()->remove_all_fees();
        }

        $disable_persistence = static function () {
            return false;
        };
        $settings = get_option("woocommerce_{$payment_method}_settings", []);
        if (! is_array($settings)) {
            $settings = [];
        }
        $calculate_wallet_fee = static function ($cart) use ($temporary_cart, $settings) {
            if ($cart !== $temporary_cart) {
                return;
            }

            do_action(
                'buckaroo_cart_calculate_fees',
                $cart,
                $settings['extrachargeamount'] ?? 0,
                $settings['feetax'] ?? ''
            );
        };
        $persistence_disabled = false;
        $fee_hook_added = false;

        try {
            $woocommerce->cart = $temporary_cart;
            if ($live_session) {
                $woocommerce->session = clone $live_session;
                $woocommerce->session->set('chosen_payment_method', $payment_method);
            }
            if ($live_customer) {
                $woocommerce->customer = clone $live_customer;
                if ($customer_location !== []) {
                    $woocommerce->customer->set_billing_location(
                        $customer_location['billing']['country'],
                        $customer_location['billing']['state'],
                        $customer_location['billing']['postcode'],
                        $customer_location['billing']['city']
                    );
                    $woocommerce->customer->set_shipping_location(
                        $customer_location['shipping']['country'],
                        $customer_location['shipping']['state'],
                        $customer_location['shipping']['postcode'],
                        $customer_location['shipping']['city']
                    );
                    $shipping->packages = [];
                }
            }

            add_filter('woocommerce_persistent_cart_enabled', $disable_persistence, PHP_INT_MAX);
            $persistence_disabled = true;
            add_action('woocommerce_cart_calculate_fees', $calculate_wallet_fee, PHP_INT_MAX);
            $fee_hook_added = true;

            return self::withoutLiveCartAddActions(
                $live_cart,
                static function () use (
                    $temporary_cart,
                    $products,
                    $use_matching_cart,
                    $preserve_matching_cart,
                    $callback
                ) {
                    if (! $use_matching_cart) {
                        foreach ($products as $product) {
                            $quantity = $product['quantity'] - self::cartProductQuantity(
                                $temporary_cart,
                                $product
                            );
                            if ($quantity <= 0) {
                                continue;
                            }

                            $cart_item_key = $temporary_cart->add_to_cart(
                                $product['product_id'],
                                $quantity,
                                $product['variation_id'],
                                $product['attributes']
                            );

                            if ($cart_item_key === false) {
                                throw new UnexpectedValueException(
                                    'Unable to add the selected product to the express cart.'
                                );
                            }
                        }
                    }

                    $temporary_cart->calculate_totals();

                    if (
                        $preserve_matching_cart
                        && ! self::cartCoversProducts($temporary_cart, $products)
                    ) {
                        throw new UnexpectedValueException(
                            'Wallet products do not match the calculated express cart.'
                        );
                    }

                    return call_user_func($callback, $temporary_cart);
                }
            );
        } finally {
            if ($fee_hook_added) {
                remove_action('woocommerce_cart_calculate_fees', $calculate_wallet_fee, PHP_INT_MAX);
            }
            if ($persistence_disabled) {
                remove_filter('woocommerce_persistent_cart_enabled', $disable_persistence, PHP_INT_MAX);
            }

            $woocommerce->cart = $live_cart;
            $woocommerce->session = $live_session;
            $woocommerce->customer = $live_customer;

            if ($shipping && $shipping_packages !== null) {
                $shipping->packages = $shipping_packages;
            }
        }
    }

    private static function customerLocation(array $source): array
    {
        if (isset($source['billing']) || isset($source['shipping'])) {
            if (! is_array($source['billing'] ?? null) || ! is_array($source['shipping'] ?? null)) {
                throw new Exception('Invalid customer location');
            }

            return [
                'billing' => self::addressLocation($source['billing']),
                'shipping' => self::addressLocation($source['shipping']),
            ];
        }

        $location = self::addressLocation($source);

        return array_filter($location) === []
            ? []
            : ['billing' => $location, 'shipping' => $location];
    }

    private static function addressLocation(array $source): array
    {
        $values = [
            'country' => $source['country_code'] ?? $source['country'] ?? '',
            'state' => $source['state'] ?? '',
            'postcode' => $source['postcode'] ?? '',
            'city' => $source['city'] ?? '',
        ];
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                throw new Exception('Invalid customer location');
            }
        }

        return [
            'country' => strtoupper(sanitize_text_field((string) $values['country'])),
            'state' => sanitize_text_field((string) $values['state']),
            'postcode' => sanitize_text_field((string) $values['postcode']),
            'city' => sanitize_text_field((string) $values['city']),
        ];
    }

    private static function detachedValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::detachedValue($item);
            }

            return $value;
        }

        if (is_object($value)) {
            $value = clone $value;
            foreach (get_object_vars($value) as $key => $item) {
                $value->{$key} = self::detachedValue($item);
            }
        }

        return $value;
    }

    private static function cartCoversProducts($cart, array $products): bool
    {
        $cart_products = [];
        foreach ($cart->get_cart_contents() as $cart_item) {
            $attributes = $cart_item['variation'] ?? [];
            ksort($attributes);
            $cart_products[] = [
                'product_id' => (int) $cart_item['product_id'],
                'variation_id' => (int) $cart_item['variation_id'],
                'quantity' => (int) $cart_item['quantity'],
                'attributes' => $attributes,
            ];
        }

        foreach ($products as &$product) {
            ksort($product['attributes']);
        }
        unset($product);

        $sort_products = static function (array &$items): void {
            usort(
                $items,
                static function (array $left, array $right): int {
                    return strcmp(wp_json_encode($left), wp_json_encode($right));
                }
            );
        };
        $sort_products($cart_products);
        $sort_products($products);

        return $cart_products !== [] && $cart_products === $products;
    }

    private static function cartProductQuantity($cart, array $product): int
    {
        $quantity = 0;
        $attributes = $product['attributes'];
        ksort($attributes);

        foreach ($cart->get_cart_contents() as $cart_item) {
            $cart_attributes = $cart_item['variation'] ?? [];
            ksort($cart_attributes);
            if (
                (int) $cart_item['product_id'] === $product['product_id']
                && (int) $cart_item['variation_id'] === $product['variation_id']
                && $cart_attributes === $attributes
            ) {
                $quantity += (int) $cart_item['quantity'];
            }
        }

        return $quantity;
    }

    private static function productsFromCart($cart): array
    {
        return array_values(
            array_map(
                static function (array $cart_item): array {
                    return [
                        'product_id' => (int) $cart_item['product_id'],
                        'variation_id' => (int) $cart_item['variation_id'],
                        'quantity' => (int) $cart_item['quantity'],
                        'attributes' => $cart_item['variation'] ?? [],
                    ];
                },
                $cart->get_cart_contents()
            )
        );
    }

    private static function validatedItems(array $items): array
    {
        $products = [];

        foreach ($items as $item) {
            if (! is_array($item) || ($item['type'] ?? '') !== 'product') {
                continue;
            }
            if (! isset($item['id'], $item['quantity']) || ! is_numeric($item['id'])) {
                throw new Exception('Invalid product item');
            }

            $product = wc_get_product(absint($item['id']));
            if (! $product) {
                throw new Exception('Invalid product item');
            }
            if (
                isset($item['price'])
                && (! is_numeric($item['price']) || ((float) $item['price'] === 0.0 && (float) $product->get_price() > 0.0))
            ) {
                throw new Exception('Invalid product item');
            }

            $products[] = self::validatedProduct(
                [
                    'product_id' => $product->is_type('variation') ? $product->get_parent_id() : $product->get_id(),
                    'variation_id' => $product->get_id(),
                    'quantity' => $item['quantity'],
                    'attributes' => $item['attributes'] ?? [],
                ]
            );
        }

        if ($products === []) {
            throw new Exception('No valid product items');
        }

        return $products;
    }

    private static function validatedProduct(array $request): array
    {
        if (! isset($request['product_id']) || ! is_numeric($request['product_id'])) {
            throw new Exception('Invalid product_id');
        }
        if (isset($request['variation_id']) && ! is_numeric($request['variation_id'])) {
            throw new Exception('Invalid variation_id');
        }
        if (! isset($request['quantity']) || ! is_numeric($request['quantity']) || $request['quantity'] <= 0) {
            throw new Exception('Invalid quantity');
        }
        if (isset($request['attributes']) && ! is_array($request['attributes'])) {
            throw new Exception('Invalid attributes');
        }

        $product_id = absint($request['product_id']);
        $variation_id = isset($request['variation_id']) ? absint($request['variation_id']) : 0;
        if ($variation_id === $product_id) {
            $variation_id = 0;
        }

        $product = wc_get_product($product_id);
        if (! $product) {
            throw new Exception('Invalid product_id');
        }

        $attributes = self::validatedAttributes(
            $product,
            $variation_id,
            $request['attributes'] ?? []
        );

        return [
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => (int) $request['quantity'],
            'attributes' => $attributes,
        ];
    }

    private static function validatedAttributes($product, int $variation_id, array $attributes): array
    {
        if ($variation_id === 0) {
            if ($attributes !== []) {
                throw new Exception('Invalid attributes');
            }

            return [];
        }

        $variation = wc_get_product($variation_id);
        if (! $variation || ! $variation->is_type('variation') || $variation->get_parent_id() !== $product->get_id()) {
            throw new Exception('Invalid variation_id');
        }

        $allowed_attributes = [];
        foreach ($product->get_attributes() as $attribute) {
            if (! $attribute instanceof WC_Product_Attribute || ! $attribute->get_variation()) {
                continue;
            }

            $allowed_attributes[wc_variation_attribute_name($attribute->get_name())] = $attribute;
        }

        $validated = [];
        foreach ($attributes as $key => $value) {
            if (! is_string($key) || ! isset($allowed_attributes[$key]) || ! is_scalar($value)) {
                throw new Exception('Invalid attributes');
            }

            $attribute = $allowed_attributes[$key];
            $value = wp_unslash((string) $value);
            $validated[$key] = $attribute->is_taxonomy()
                ? sanitize_title($value)
                : html_entity_decode(wc_clean($value), ENT_QUOTES, get_bloginfo('charset'));
        }

        return $validated;
    }

    private static function withoutLiveCartAddActions($live_cart, callable $callback)
    {
        global $wp_filter;

        if (! isset($wp_filter['woocommerce_add_to_cart'])) {
            return call_user_func($callback);
        }

        $hook = $wp_filter['woocommerce_add_to_cart'];
        $filtered_hook = clone $hook;
        foreach ($filtered_hook->callbacks as &$callbacks) {
            foreach ($callbacks as $id => $registered_callback) {
                $function = $registered_callback['function'];
                if (
                    is_array($function)
                    && isset($function[0])
                    && ($function[0] === $live_cart || $function[0] instanceof \WC_Cart_Session)
                ) {
                    unset($callbacks[$id]);
                }
            }
        }
        unset($callbacks);

        $wp_filter['woocommerce_add_to_cart'] = $filtered_hook;
        try {
            return call_user_func($callback);
        } finally {
            $wp_filter['woocommerce_add_to_cart'] = $hook;
        }
    }
}
