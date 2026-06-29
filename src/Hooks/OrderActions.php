<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Core\PaymentGatewayRegistry;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Order\OrderCaptureRefund;
use Buckaroo\Woocommerce\Services\Helper;
use WC_Cart;

class OrderActions
{
    public function __construct()
    {
        add_action('order_edit_form_top', [$this, 'handleOrderInTestMode']);
        add_action('edit_form_top', [$this, 'handleOrderInTestMode']);
        add_action('wp_ajax_woocommerce_cart_calculate_fees', [$this, 'calculate_order_fees']);
        add_action('wp_ajax_nopriv_woocommerce_cart_calculate_fees', [$this, 'calculate_order_fees']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'calculate_order_fees']);
        add_action('buckaroo_cart_calculate_fees', [$this, 'add_fee_to_cart'], 10, 3);

        add_action('wp_ajax_order_capture', [$this, 'handleOrderCapture']);
        new OrderCaptureRefund();
    }

    public function handleOrderInTestMode($post): void
    {
        $isOrderInstance = Helper::isOrderInstance($post);

        if (! $isOrderInstance && ($post->post_type ?? '') !== 'shop_order') {
            return;
        }

        $orderId = $isOrderInstance ? $post->get_id() : $post->ID;

        if (get_post_meta($orderId, '_buckaroo_order_in_test_mode', true) !== '1') {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__('The payment for this order was made in test mode')
        );
    }

    public function handleOrderCapture(): void
    {
        if (! check_ajax_referer('order-item', 'security', false)) {
            wp_send_json(
                [
                    'errors' => [
                        'error_capture' => [
                            [esc_html__('Invalid security token. Please reload the page and try again.', 'wc-buckaroo-bpe-gateway')],
                        ],
                    ],
                ]
            );
        }

        if (! current_user_can('edit_shop_orders')) {
            wp_send_json(
                [
                    'errors' => [
                        'error_capture' => [
                            [esc_html__('You are not allowed to capture this order.', 'wc-buckaroo-bpe-gateway')],
                        ],
                    ],
                ]
            );
        }

        if (! isset($_POST['order_id'])) {
            wp_send_json(
                [
                    'errors' => [
                        'error_capture' => [
                            [esc_html__('A valid order number is required')],
                        ],
                    ],
                ]
            );
        }

        $orderId = (int) sanitize_text_field($_POST['order_id']);
        $gateway = $this->resolveCapturableGateway($orderId);

        if ($gateway === null) {
            wp_send_json(
                [
                    'errors' => [
                        'error_capture' => [
                            [esc_html__('Could not resolve a Buckaroo gateway for this order. The order may have been created with an older version of the plugin.', 'wc-buckaroo-bpe-gateway')],
                        ],
                    ],
                ]
            );
        }

        if ($gateway->capturable && $gateway->canShowCaptureForm($orderId)) {
            wp_send_json(
                $gateway->process_capture($orderId)
            );
        }

        wp_send_json(
            [
                'errors' => [
                    'error_capture' => [
                        [esc_html__('This order is not in a capturable state.', 'wc-buckaroo-bpe-gateway')],
                    ],
                ],
            ]
        );
    }

    /**
     * Resolve the Buckaroo gateway for an order, falling back to the WooCommerce
     * payment method id (e.g. `buckaroo_klarnapay`) when the legacy
     * `_wc_order_selected_payment_method` meta value cannot be matched against
     * the gateway registry. This keeps capture working for orders created with
     * earlier plugin versions that wrote display titles into that meta.
     */
    private function resolveCapturableGateway(int $orderId): ?AbstractPaymentGateway
    {
        $registry = new PaymentGatewayRegistry();

        $primary = get_post_meta($orderId, '_wc_order_selected_payment_method', true);
        if (is_string($primary) && $primary !== '') {
            $gateway = $registry->newGatewayInstance($primary);
            if ($gateway instanceof AbstractPaymentGateway) {
                return $gateway;
            }
        }

        $order = Helper::findOrder($orderId);
        if ($order) {
            $methodId = (string) $order->get_payment_method();
            if (strpos($methodId, 'buckaroo_') === 0) {
                $fallbackKey = substr($methodId, strlen('buckaroo_'));
                $gateway = $registry->newGatewayInstance($fallbackKey);
                if ($gateway instanceof AbstractPaymentGateway) {
                    return $gateway;
                }
            }
        }

        return null;
    }

    /**
     * Calculates fees on items in shopping cart. (e.g. Taxes)
     *
     * @return void
     */
    public function calculate_order_fees()
    {
        if (isset($_POST['method'])) {
            WC()->session->set('chosen_payment_method', $_POST['method']);
        }

        $cart = WC()->cart;
        $chosen_payment_method = WC()->session->chosen_payment_method;

        // Only our gateways add a Buckaroo fee. Bail before the (relatively
        // expensive) gateway resolution when another method is selected, so
        // switching between non-Buckaroo methods stays fast.
        if (strpos((string) $chosen_payment_method, 'buckaroo_') !== 0) {
            return;
        }

        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

        // no payments available
        if (empty($available_gateways)) {
            return;
        }

        // no gateway found or not ours
        if (
            ! isset($available_gateways[$chosen_payment_method]) ||
            ! $available_gateways[$chosen_payment_method] instanceof AbstractPaymentGateway
        ) {
            return;
        }

        $gateway = $available_gateways[$chosen_payment_method];

        $this->add_fee_to_cart(
            $cart,
            $gateway->get_option('extrachargeamount', 0),
            $gateway->get_option('feetax', '')
        );
    }

    /**
     * Add fee to cart
     *
     * @param  WC_Cart  $cart
     * @param  string  $gateway_extrachargeamount
     * @param  string  $gateway_feetax
     * @return void
     */
    public function add_fee_to_cart($cart, $gateway_extrachargeamount, $gateway_feetax)
    {
        // no fee available
        if (
            ! is_scalar($gateway_extrachargeamount) ||
            empty($gateway_extrachargeamount) ||
            (float) $gateway_extrachargeamount === 0
        ) {
            return;
        }

        // not a valid value
        if (! $this->is_extrachargeamount_valid($gateway_extrachargeamount)) {
            return;
        }

        $subtotal = $cart->get_cart_contents_total();
        $is_percentage = strpos($gateway_extrachargeamount, '%') !== false;
        $extra_charge_amount = (float) str_replace('%', '', $gateway_extrachargeamount);

        // percentage not 0
        if ($extra_charge_amount === 0) {
            return;
        }

        if ($is_percentage) {
            $extra_charge_amount = round($subtotal * $extra_charge_amount / 100, 2);
        }

        $feedName = __('Payment fee', 'wc-buckaroo-bpe-gateway');
        $feedId = sanitize_title($feedName);

        $fee = $this->get_fee($cart, $feedId);

        if ($fee === null) {
            $cart->add_fee(
                $feedName,
                $extra_charge_amount,
                true,
                $gateway_feetax
            );
        } else {
            $fee->amount = $extra_charge_amount;
        }
    }

    /**
     * Check if extrachangeamount is valid
     *
     * @param  string  $value
     * @return bool
     */
    public function is_extrachargeamount_valid($value)
    {
        return (bool) preg_match('/^\d+(?:\.\d+)?%?$/', $value);
    }

    /**
     * Get fee from cart by id
     *
     * @param  WC_Cart  $cart
     * @param  string  $id
     * @return array|null
     */
    protected function get_fee($cart, $id)
    {
        foreach ($cart->get_fees() as $fee) {
            if ($fee->id === $id) {
                return $fee;
            }
        }

        return null;
    }
}
