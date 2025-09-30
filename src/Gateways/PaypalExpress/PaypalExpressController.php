<?php

namespace Buckaroo\Woocommerce\Gateways\PaypalExpress;

use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Gateways\ExpressPaymentManager;
use Throwable;

/**
 * Core for dealing with paypal express button
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
class PaypalExpressController
{
    public const LOCATION_NONE = 'none';

    public const LOCATION_PRODUCT = 'product';

    public const LOCATION_CART = 'cart';

    public const LOCATION_CHECKOUT = 'checkout';

    /**
     * Paypal setting
     *
     * @var array
     */
    protected $settings;

    /**
     * Handle order
     *
     * @var PaypalExpressOrder
     */
    protected $order;

    /**
     * Handle shipping calculation on cart
     *
     * @var PaypalExpressShipping
     */
    protected $shipping;

    /**
     * Handle storing and restoring cart for total calculation
     *
     * @var PaypalExpressCart
     */
    protected $cart;

    public function __construct($shipping, $order, $cart)
    {
        $this->shipping = $shipping;
        $this->order = $order;
        $this->cart = $cart;

        $this->get_settings();

        if (! $this->is_active()) {
            return;
        }
        $this->hook_ajax_calls();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        $this->hook_active_buttons();
    }

    /**
     * enqueue the js
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        if (
            class_exists('WC_Order') && (
                is_product() ||
                is_checkout() ||
                is_cart()
            )
        ) {
            wp_enqueue_script(
                'buckaroo_paypal_express',
                plugin_dir_url(BK_PLUGIN_FILE) . '/library/js/paypal_express.js',
                ['buckaroo_sdk'],
                Plugin::VERSION,
                true
            );
            wp_localize_script(
                'buckaroo_paypal_express',
                'buckaroo_paypal_express',
                [
                    'set_shipping_nonce' => wp_create_nonce('express-set-shipping'),
                    'cart_total_nonce' => wp_create_nonce('express-cart-totals'),
                    'send_order_nonce' => wp_create_nonce('express-send_order'),
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'currency' => get_woocommerce_currency(),
                    'websiteKey' => $this->get_website_key(),
                    'merchant_id' => $this->get_merchant_id(),
                    'page' => $this->determine_page(),
                    'i18n' => [
                        'cancel_error_message' => __('You have canceled the payment request', 'wc-buckaroo-bpe-gateway'),
                        'cannot_create_payment' => __('Cannot create payment', 'wc-buckaroo-bpe-gateway'),
                        'merchant_id_required' => __('PayPal merchant id is required', 'wc-buckaroo-bpe-gateway'),
                    ],
                ]
            );
        }
    }

    /**
     * Check if paypal express is active
     *
     * @return bool
     */
    protected function is_active()
    {
        return $this->settings['enabled'] == 'yes' &&
            ! (count($this->settings['express']) === 1 && in_array(self::LOCATION_NONE, $this->settings['express']));
    }

    /**
     * Get paypal saved settings
     *
     * @return void
     */
    protected function get_settings()
    {
        $default = [
            'enabled' => 'no',
            'express' => ['none'],
        ];
        $settings = get_option('woocommerce_buckaroo_paypal_settings', []);

        if (! isset($settings['express']) || ! is_array($settings['express'])) {
            $settings['express'] = ['none'];
        }

        $this->settings = array_merge($default, $settings);
    }

    /**
     * Hook buttons into woocommerce pages
     *
     * @return void
     */
    protected function hook_active_buttons()
    {
        $expressManager = ExpressPaymentManager::getInstance();

        if ($this->active_on_page(self::LOCATION_PRODUCT)) {
            $expressManager->registerExpressPayment('paypal_express', [$this, 'render_button'], 'product');
        }
        if ($this->active_on_page(self::LOCATION_CART)) {
            $expressManager->registerExpressPayment('paypal_express', [$this, 'render_button'], 'cart');
        }
        if ($this->active_on_page(self::LOCATION_CHECKOUT)) {
            $expressManager->registerExpressPayment('paypal_express', [$this, 'render_button'], 'checkout');
        }
    }

    /**
     * Hook ajax call
     *
     * @return void
     */
    public function hook_ajax_calls()
    {
        add_action('wp_ajax_buckaroo_paypal_express_order', [$this, 'send_order']);
        add_action('wp_ajax_nopriv_buckaroo_paypal_express_order', [$this, 'send_order']);

        add_action('wp_ajax_buckaroo_paypal_express_set_shipping', [$this, 'add_shipping']);
        add_action('wp_ajax_nopriv_buckaroo_paypal_express_set_shipping', [$this, 'add_shipping']);

        add_action('wp_ajax_buckaroo_paypal_express_get_cart_total', [$this, 'get_cart_total']);
        add_action('wp_ajax_nopriv_buckaroo_paypal_express_get_cart_total', [$this, 'get_cart_total']);
    }

    public function add_shipping()
    {
        check_ajax_referer('express-set-shipping', 'set_shipping_nonce');
        try {
            if ($this->on_product_page()) {
                $this->shipping->create_cart_for_product_page();
            }
            wp_send_json(
                [
                    'error' => false,
                    'data' => [
                        'value' => $this->shipping->get_cart_total_breakdown(),
                    ],
                ]
            );
        } catch (PaypalExpressException $th) {
            wp_send_json(
                [
                    'error' => true,
                    'message' => $th->getMessage(),
                ]
            );
        } catch (Throwable $th) {
            Logger::log(__METHOD__, $th->getMessage());
            wp_send_json(
                [
                    'error' => true,
                    'message' => 'Internal buckaroo error',
                ]
            );
        }
    }

    /**
     * Get total cart price
     *
     * @return void
     */
    public function get_cart_total()
    {
        check_ajax_referer('express-cart-totals', 'cart_total_nonce');
        try {
            if ($this->on_product_page()) {
                $this->cart->store_current();
                $this->shipping->create_cart_for_product_page();
            }

            $total = WC()->cart->get_total(false);

            if ($this->on_product_page()) {
                $this->cart->restore();
            }

            wp_send_json(
                [
                    'error' => false,
                    'data' => [
                        'total' => number_format($total, 2),
                    ],
                ]
            );
        } catch (Throwable $th) {
            Logger::log(__METHOD__, $th->getMessage());
            wp_send_json(
                [
                    'error' => true,
                    'message' => 'Cannot calculate cart total',
                ]
            );
        }
    }

    /**
     * Create order from ajax call
     *
     * @return void
     */
    public function send_order()
    {
        check_ajax_referer('express-send_order', 'send_order_nonce');
        if (! isset($_POST['orderId'])) {
            wp_send_json(
                [
                    'error' => true,
                    'message' => 'No paypal express order id provided',
                ]
            );
        }
        try {
            $response = $this->order->create_and_send(sanitize_text_field($_POST['orderId']));
            $this->display_any_notices();

            wp_send_json(
                [
                    'error' => false,
                    'data' => $response,
                ]
            );
        } catch (Throwable $th) {
            Logger::log(__METHOD__, $th->getMessage());
            wp_send_json(
                [
                    'error' => true,
                    'message' => 'Cannot process buckaroo payment',
                ]
            );
        }
    }

    /**
     * Display any error notices that we may have if the payment fails
     *
     * @return void
     */
    protected function display_any_notices()
    {
        $notices = wc_get_notices('error');
        wc_clear_notices();

        $messages = [];
        if (is_array($notices)) {
            foreach ($notices as $notice) {
                if (is_string($notice)) {
                    $messages[] = $notice;
                }

                if (
                    is_array($notice) &&
                    array_key_exists('notice', $notice) &&
                    is_string($notice['notice'])
                ) {
                    $messages[] = $notice['notice'];
                }
            }
        }

        if (count($messages)) {
            wp_send_json(
                [
                    'error' => true,
                    'message' => implode('</br>', $messages),
                ]
            );
        }
    }

    /**
     * Check if on product page
     *
     * @return bool
     */
    protected function on_product_page()
    {
        return isset($_POST['page']) && sanitize_text_field($_POST['page']) === self::LOCATION_PRODUCT;
    }

    /**
     * Check if button is active on page
     *
     * @param  string  $page
     * @return bool
     */
    protected function active_on_page($page)
    {
        return in_array($page, $this->settings['express']);
    }

    /**
     * Render express button
     *
     * @return void
     */
    public function render_button()
    {
        echo '<div class="buckaroo-paypal-express"></div>';
    }

    /**
     * Get website key
     *
     * @return void
     */
    protected function get_website_key()
    {
        $masterSettings = get_option('woocommerce_buckaroo_mastersettings_settings', null);
        if ($masterSettings !== null) {
            return $masterSettings['merchantkey'];
        }
    }

    /**
     * Get paypal merchant id
     *
     * @return string|null
     */
    protected function get_merchant_id()
    {
        if (isset($this->settings['express_merchant_id']) && strlen(trim($this->settings['express_merchant_id']))) {
            return $this->settings['express_merchant_id'];
        }
    }

    protected function determine_page()
    {
        if (is_product()) {
            return self::LOCATION_PRODUCT;
        }
        if (is_cart()) {
            return self::LOCATION_CART;
        }
        if (is_checkout()) {
            return self::LOCATION_CHECKOUT;
        }
    }
}
