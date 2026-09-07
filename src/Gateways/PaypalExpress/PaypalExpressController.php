<?php

namespace Buckaroo\Woocommerce\Gateways\PaypalExpress;

use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Gateways\ExpressProductCart;
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

    public function __construct($shipping, $order)
    {
        $this->shipping = $shipping;
        $this->order = $order;

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
        if (! class_exists('WC_Order')) {
            return;
        }

        $shouldLoad = (is_product() && $this->active_on_page(self::LOCATION_PRODUCT))
            || (is_cart() && $this->active_on_page(self::LOCATION_CART))
            || (is_checkout() && $this->active_on_page(self::LOCATION_CHECKOUT));

        if (! $shouldLoad) {
            return;
        }

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
                'is_test' => $this->is_test_mode(),
                'page' => $this->determine_page(),
                'i18n' => [
                    'cancel_error_message' => __('You have canceled the payment request', 'wc-buckaroo-bpe-gateway'),
                    'cannot_create_payment' => __('Cannot create payment', 'wc-buckaroo-bpe-gateway'),
                    'merchant_id_required' => __('PayPal merchant id is required', 'wc-buckaroo-bpe-gateway'),
                    'select_product_options' => __(
                        'Please choose product options before using PayPal.',
                        'wc-buckaroo-bpe-gateway'
                    ),
                ],
            ]
        );
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
            $customer_context = $this->shipping->get_customer_context();
            $product_request = null;
            if ($this->on_product_page()) {
                $product_request = $this->shipping->get_product_request();
                $value = ExpressProductCart::calculate(
                    array_merge($product_request, $customer_context),
                    'buckaroo_paypal',
                    function ($cart) {
                        return $this->shipping->get_cart_total_breakdown($cart);
                    }
                );
            } else {
                $value = ExpressProductCart::calculateCurrent(
                    'buckaroo_paypal',
                    function ($cart) {
                        return $this->shipping->get_cart_total_breakdown($cart);
                    },
                    $customer_context
                );
            }
            wp_send_json(
                [
                    'error' => false,
                    'data' => [
                        'value' => $value,
                        'quote_token' => $this->create_quote(
                            $value['value'],
                            $product_request,
                            $customer_context
                        ),
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
            $product_request = null;
            if ($this->on_product_page()) {
                $product_request = $this->shipping->get_product_request();
                $total = ExpressProductCart::calculate(
                    $product_request,
                    'buckaroo_paypal',
                    static function ($cart) {
                        return $cart->get_total(false);
                    }
                );
            } else {
                $total = ExpressProductCart::calculateCurrent(
                    'buckaroo_paypal',
                    static function ($cart) {
                        return $cart->get_total(false);
                    }
                );
            }

            wp_send_json(
                [
                    'error' => false,
                    'data' => [
                        'total' => number_format($total, 2, '.', ''),
                        'quote_token' => $this->create_quote($total, $product_request, []),
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

            return;
        }
        try {
            $quote = $this->validated_quote();
            $this->consume_quote();
            $result = $this->shipping->with_cart_for_order(
                $this->on_product_page(),
                function ($cart) use ($quote) {
                    $order_total = number_format((float) $cart->get_total(false), 2, '.', '');
                    if (! hash_equals($quote['total'], $order_total)) {
                        throw new PaypalExpressException('PayPal quote total has changed');
                    }
                    $response = $this->order->create_and_send(
                        sanitize_text_field(wp_unslash($_POST['orderId']))
                    );
                    $error = $this->get_error_notices();
                    if ($error !== null) {
                        throw new PaypalExpressException($error);
                    }
                    if (! is_array($response) || empty($response['redirect'])) {
                        throw new PaypalExpressException('Cannot create PayPal payment');
                    }

                    return $response;
                }
            );

            wp_send_json(
                [
                    'error' => false,
                    'data' => $result,
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
                    'message' => 'Cannot process buckaroo payment',
                ]
            );
        }
    }

    /**
     * Create a signed snapshot of the cart state used for a PayPal quote.
     *
     * @param float|string $total Cart total.
     * @param array|null $product_request Selected product details.
     * @param array $customer_location Selected shipping location.
     * @return string
     */
    private function create_quote($total, $product_request, array $customer_location): string
    {
        $payload = [
            'expires' => time() + (10 * MINUTE_IN_SECONDS),
            'jti' => bin2hex(random_bytes(16)),
            'page' => $this->on_product_page() ? self::LOCATION_PRODUCT : $this->get_request_page(),
            'product' => $product_request,
            'cart_hash' => $product_request === null ? WC()->cart->get_cart_hash() : null,
            'customer_location' => $customer_location,
            'total' => number_format((float) $total, 2, '.', ''),
        ];
        $json = wp_json_encode($payload);
        if (! is_string($json)) {
            throw new PaypalExpressException('Cannot create PayPal quote');
        }
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, wp_salt('nonce'));

        return $encoded . '.' . $signature;
    }

    /**
     * Validate that the approved request still matches its signed quote.
     *
     * @return array
     *
     * @throws PaypalExpressException When the quote is invalid or stale.
     */
    private function validated_quote(): array
    {
        if (! isset($_POST['quote_token']) || ! is_string($_POST['quote_token'])) {
            throw new PaypalExpressException('PayPal quote is required');
        }
        $token = sanitize_text_field(wp_unslash($_POST['quote_token']));
        $parts = explode('.', $token, 2);
        if (
            count($parts) !== 2
            || ! hash_equals(hash_hmac('sha256', $parts[0], wp_salt('nonce')), $parts[1])
        ) {
            throw new PaypalExpressException('PayPal quote is invalid');
        }

        $padding = (4 - (strlen($parts[0]) % 4)) % 4;
        $json = base64_decode(strtr($parts[0], '-_', '+/') . str_repeat('=', $padding), true);
        $quote = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($quote) || ! isset($quote['expires'], $quote['page'], $quote['total'])) {
            throw new PaypalExpressException('PayPal quote is invalid');
        }
        if (! is_numeric($quote['expires']) || (int) $quote['expires'] < time()) {
            throw new PaypalExpressException('PayPal quote has expired');
        }

        $product_request = $this->on_product_page() ? $this->shipping->get_product_request() : null;
        $customer_location = isset($_POST['shipping_data']['shipping_address'])
            ? $this->shipping->get_customer_context()
            : [];
        $expected = [
            'page' => $this->on_product_page() ? self::LOCATION_PRODUCT : $this->get_request_page(),
            'product' => $product_request,
            'cart_hash' => $product_request === null ? WC()->cart->get_cart_hash() : null,
            'customer_location' => $customer_location,
        ];
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $quote) || $quote[$key] !== $value) {
                throw new PaypalExpressException('PayPal quote no longer matches the order');
            }
        }

        return $quote;
    }

    /**
     * Mark a signed quote as consumed before creating its WooCommerce order.
     *
     * @return void
     *
     * @throws PaypalExpressException When the quote was already consumed.
     */
    private function consume_quote(): void
    {
        $token = sanitize_text_field(wp_unslash($_POST['quote_token']));
        $quote_hash = hash('sha256', $token);
        $transient = 'buckaroo_paypal_express_quote_' . $quote_hash;
        $value_option = '_transient_' . $transient;
        $timeout_option = '_transient_timeout_' . $transient;
        $timeout = (int) get_option($timeout_option, 0);
        if ($timeout > 0 && $timeout < time()) {
            delete_option($value_option);
            delete_option($timeout_option);
        }
        if (! add_option($value_option, 1, '', false)) {
            throw new PaypalExpressException('PayPal quote was already used');
        }

        add_option($timeout_option, time() + (10 * MINUTE_IN_SECONDS), '', false);
    }

    /**
     * Get a supported non-product PayPal button location.
     *
     * @return string
     */
    private function get_request_page(): string
    {
        $page = isset($_POST['page']) ? sanitize_text_field(wp_unslash($_POST['page'])) : '';

        return in_array($page, [self::LOCATION_CART, self::LOCATION_CHECKOUT], true)
            ? $page
            : self::LOCATION_CART;
    }

    /**
     * Collect error notices raised while creating the isolated order.
     *
     * @return string|null
     */
    protected function get_error_notices()
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

        return count($messages) ? implode('</br>', $messages) : null;
    }

    /**
     * Check if on product page
     *
     * @return bool
     */
    protected function on_product_page()
    {
        return isset($_POST['page'])
            && sanitize_text_field(wp_unslash($_POST['page'])) === self::LOCATION_PRODUCT;
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
     * Whether the PayPal gateway is running in test (sandbox) mode.
     *
     * @return bool
     */
    protected function is_test_mode()
    {
        return isset($this->settings['mode']) && strtolower((string) $this->settings['mode']) === 'test';
    }

    /**
     * Get paypal merchant id for the active environment.
     *
     * In test mode the sandbox merchant id is used, falling back to the live
     * merchant id when no sandbox value is configured. The SDK selects the
     * matching (sandbox/live) PayPal client id from the isTestMode flag.
     *
     * @return string|null
     */
    protected function get_merchant_id()
    {
        if ($this->is_test_mode()) {
            $sandbox = $this->get_setting_value('express_sandbox_merchant_id');
            if ($sandbox !== null) {
                return $sandbox;
            }
        }

        return $this->get_setting_value('express_merchant_id');
    }

    /**
     * Return a trimmed, non-empty setting value or null.
     *
     * @param  string  $key
     * @return string|null
     */
    protected function get_setting_value($key)
    {
        if (isset($this->settings[$key]) && strlen(trim((string) $this->settings[$key]))) {
            return trim((string) $this->settings[$key]);
        }

        return null;
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
