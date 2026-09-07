<?php

namespace Buckaroo\Woocommerce\Gateways\PaypalExpress;

use Buckaroo\Woocommerce\Gateways\ExpressProductCart;

/**
 * PayPal express shipping class
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 3.0.0
 *
 * @link      https://www.buckaroo.eu/
 */
class PaypalExpressShipping
{
    /**
     * Get the selected product and variation details from the request.
     *
     * @return array
     */
    public function get_product_request(): array
    {
        $order_data = $this->get_order_data();
        $attributes = [];
        foreach ($order_data as $key => $value) {
            if (strpos($key, 'attribute_') === 0) {
                $attributes[$key] = $value;
            }
        }
        ksort($attributes);

        return [
            'product_id' => $this->get_required_value($order_data, 'add-to-cart'),
            'variation_id' => $this->get_value($order_data, 'variation_id', 0),
            'quantity' => $this->get_required_value($order_data, 'quantity'),
            'attributes' => $attributes,
        ];
    }

    /**
     * Get the PayPal shipping location from the request.
     *
     * @return array
     */
    public function get_customer_location(): array
    {
        $address_data = $this->get_address_data();

        return [
            'country' => $this->get_required_value($address_data, 'country_code'),
            'state' => $this->get_required_value($address_data, 'state'),
            'postcode' => $this->get_required_value($address_data, 'postal_code'),
            'city' => $this->get_required_value($address_data, 'city'),
        ];
    }

    /**
     * Keep the shopper's billing location while applying PayPal shipping.
     *
     * @return array
     */
    public function get_customer_context(): array
    {
        return [
            'billing' => [
                'country' => WC()->customer->get_billing_country(),
                'state' => WC()->customer->get_billing_state(),
                'postcode' => WC()->customer->get_billing_postcode(),
                'city' => WC()->customer->get_billing_city(),
            ],
            'shipping' => $this->get_customer_location(),
        ];
    }

    /**
     * Run order creation against the isolated cart used for the approved quote.
     *
     * @param bool $product_page Whether the button is on a product page.
     * @param callable $callback Order creation callback.
     * @return mixed
     */
    public function with_cart_for_order(bool $product_page, callable $callback)
    {
        $customer_location = isset($_POST['shipping_data']['shipping_address'])
            ? $this->get_customer_context()
            : [];

        if ($product_page) {
            return ExpressProductCart::calculate(
                array_merge($this->get_product_request(), $customer_location),
                'buckaroo_paypal',
                $callback
            );
        }

        $order_result = ExpressProductCart::calculateCurrent(
            'buckaroo_paypal',
            static function ($cart) use ($callback): array {
                return [
                    'value' => call_user_func($callback, $cart),
                    'cart_emptied' => $cart->is_empty(),
                ];
            },
            $customer_location
        );

        if ($order_result['cart_emptied']) {
            WC()->cart->empty_cart();
        }

        return $order_result['value'];
    }

    /**
     * Get cart total brakdown by items, shipping & tax
     *
     * @param  WC_Cart  $cart
     * @return array
     */
    public function get_cart_total_breakdown($cart)
    {
        $total = $cart->get_total(false);
        $tax_total = $cart->get_total_tax();
        $shipping = $cart->get_shipping_total();
        $item_total = $total - $tax_total - $shipping;
        $currency = get_woocommerce_currency();

        return [
            'breakdown' => [
                'item_total' => [
                    'currency_code' => $currency,
                    'value' => $this->number_format($item_total),
                ],
                'shipping' => [
                    'currency_code' => $currency,
                    'value' => $this->number_format($shipping),
                ],
                'tax_total' => [
                    'currency_code' => $currency,
                    'value' => $this->number_format($tax_total),
                ],
            ],
            'currency_code' => $currency,
            'value' => $this->number_format($total),
        ];
    }

    /**
     * Format numbers to 2 decimals
     *
     * @param  float|string  $value
     * @return float
     */
    public function number_format($value)
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Get required values or throw exception
     *
     * @param  array  $data
     * @param  string  $key
     * @return mixed
     *
     * @throws Exception
     */
    protected function get_required_value($data, $key)
    {
        if (! isset($data[$key])) {
            throw new PaypalExpressException('Field is required ' . $key);
        }

        return $this->get_value($data, $key);
    }

    /**
     * Get value from array with a default
     *
     * @param  array  $data
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    protected function get_value($data, $key, $default = null)
    {
        return $data[$key] ?? $default;
    }

    /**
     * Get address data from frontend
     *
     * @return array
     */
    protected function get_address_data()
    {
        if (! isset($_POST['shipping_data']) || ! isset($_POST['shipping_data']['shipping_address'])) {
            throw new PaypalExpressException('Shipping address is required');
        }

        return wc_clean($_POST['shipping_data']['shipping_address']);
    }

    /**
     * Get formatted order data from frontend
     *
     * @return array
     */
    protected function get_order_data()
    {
        if (! isset($_POST['order_data']) || count($_POST['order_data']) === 0) {
            throw new PaypalExpressException('Empty cart, cannot create order');
        }
        $request = [];
        foreach (wc_clean($_POST['order_data']) as $data) {
            if (! isset($data['name']) || ! isset($data['value'])) {
                throw new PaypalExpressException('Invalid data format');
            }
            $request[$data['name']] = $data['value'];
        }

        return $request;
    }
}
