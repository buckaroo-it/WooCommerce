<?php

namespace Buckaroo\Woocommerce\Gateways\Googlepay;

use Buckaroo\Woocommerce\Gateways\ExpressProductCart;
use Throwable;
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
        try {
            $items = ExpressProductCart::calculate(
                $_GET,
                'buckaroo_googlepay',
                function ($cart) {
                    return self::getCartItemsForGooglePay($cart);
                }
            );
        } catch (Throwable $exception) {
            self::sendCalculationFailure();
        }

        wp_send_json(array_values($items));
    }

    public static function getItemsFromCart()
    {
        try {
            $items = ExpressProductCart::calculateCurrent(
                'buckaroo_googlepay',
                static function ($cart) {
                    return self::getCartItemsForGooglePay($cart);
                }
            );
        } catch (Throwable $exception) {
            self::sendCalculationFailure();
        }

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
                'attributes' => $cart_item['variation'] ?? [],
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

    public static function getCartTotal()
    {
        try {
            $totals = ExpressProductCart::calculateCurrent(
                'buckaroo_googlepay',
                static function ($cart) {
                    return self::getCartTotals($cart);
                }
            );
        } catch (Throwable $exception) {
            self::sendCalculationFailure();
        }

        wp_send_json($totals);
    }

    public static function getShippingMethods()
    {
        $wcGooglepayMethods = static function () {
            $packages = WC()->shipping()->get_packages();

            return $packages ? (current($packages)['rates'] ?? []) : [];
        };

        try {
            if (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
                $wc_methods = ExpressProductCart::calculate(
                    $_GET,
                    'buckaroo_googlepay',
                    $wcGooglepayMethods
                );
            } else {
                $wc_methods = ExpressProductCart::calculateCurrent(
                    'buckaroo_googlepay',
                    $wcGooglepayMethods,
                    ['country' => $_GET['country_code'] ?? '']
                );
            }
        } catch (Throwable $exception) {
            self::sendCalculationFailure();
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

    private static function getCartTotals($cart): array
    {
        $shipping_total = (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();
        $shipping_label = __('Shipping', 'wc-buckaroo-bpe-gateway');
        $packages = WC()->shipping() ? WC()->shipping()->get_packages() : [];
        $chosen_methods = WC()->session ? (array) WC()->session->get('chosen_shipping_methods') : [];
        foreach ($packages as $index => $package) {
            $rate_id = $chosen_methods[$index] ?? '';
            if ($rate_id && isset($package['rates'][$rate_id])) {
                $shipping_label = $package['rates'][$rate_id]->get_label();
                break;
            }
        }

        return [
            'total' => round((float) $cart->get_total('edit'), 2),
            'shipping' => round($shipping_total, 2),
            'shipping_label' => $shipping_label,
        ];
    }

    private static function sendCalculationFailure()
    {
        wp_send_json(
            [
                'status' => 'fail',
                'message' => __('Unable to calculate Google Pay cart.', 'wc-buckaroo-bpe-gateway'),
            ],
            400
        );
    }
}
