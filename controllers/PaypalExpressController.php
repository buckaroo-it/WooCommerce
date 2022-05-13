<?php

/**
 * Core for dealing with paypal express button
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 3.0.0
 * @link      https://www.buckaroo.eu/
 */

class Buckaroo_Paypal_Express
{
    const LOCATION_NONE = 'none';
    const LOCATION_PRODUCT = 'product';
    const LOCATION_CART = 'cart';
    const LOCATION_CHECKOUT = 'checkout';
    /**
     * Paypal setting
     *
     * @var array
     */
    protected $settings;

    /**
     * Handle order
     *
     * @var Buckaroo_Paypal_Express_Order
     */
    protected $order;

    /**
     * Handle shipping calculation on cart
     *
     * @var Buckaroo_Paypal_Express_Shipping
     */
    protected $shipping;

    /**
     * Handle storing and restoring cart for total calculation
     *
     * @var Buckaroo_Paypal_Express_Cart
     */
    protected $cart;

    public function __construct($shipping, $order, $cart)
    {
        $this->shipping = $shipping;
        $this->order = $order;
        $this->cart = $cart;
    
        $this->get_settings();

        if (!$this->is_active()) {
            return;
        }
        $this->hook_ajax_calls();
        add_action('wp_enqueue_scripts', [$this, "enqueue_scripts"]);
        $this->hook_active_buttons();
    }
    /**
     * enqueue the js 
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            'buckaroo_paypal_express',
            plugin_dir_url(BK_PLUGIN_FILE) . '/library/js/paypal_express.js',
            array('buckaroo_sdk'),
            BuckarooConfig::VERSION,
            true
        );
        wp_localize_script(
            'buckaroo_paypal_express',
            'buckaroo_paypal_express',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'currency' => get_woocommerce_currency(),
                'websiteKey' => $this->get_website_key(),
                'page' => $this->determine_page(),
                'i18n' => [
                    'cancel_error_message' => __("You have canceled the payment request", 'wc-buckaroo-bpe-gateway'),
                    'cannot_create_payment' => __("Cannot create payment" , 'wc-buckaroo-bpe-gateway')
                ]
            )
        );
    }
    /**
     * Check if paypal express is active
     *
     * @return boolean
     */
    protected function is_active()
    {
        return $this->settings['enabled'] == "yes" &&
            !(count($this->settings['express']) === 1 && in_array(self::LOCATION_NONE, $this->settings['express']));
    }
    /**
     * Get paypal saved settings
     *
     * @return void
     */
    protected function get_settings()
    {
        $default = array(
            "enabled" => "no",
            "express" => ["none"]
        );
        $settings = get_option('woocommerce_buckaroo_paypal_settings', []);
        $this->settings = array_merge($default, $settings);
    }
    /**
     * Hook buttons into woocommerce pages
     *
     * @return void
     */
    protected function hook_active_buttons()
    {
        if ($this->active_on_page(self::LOCATION_PRODUCT)) {
            add_action('woocommerce_after_add_to_cart_button', [$this, 'render_button']);
        }
        if ($this->active_on_page(self::LOCATION_CART)) {
            add_action('woocommerce_after_cart_totals', [$this, 'render_button']);
        }
        if ($this->active_on_page(self::LOCATION_CHECKOUT)) {
            add_action('woocommerce_before_checkout_form', [$this, 'render_button']);
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
        header('Content-Type: application/json');
        try {
            if ($this->on_product_page()) {
                $this->shipping->create_cart_for_product_page();
            }
            wp_die(
                json_encode([
                    "error" => false,
                    "data" => [
                        "value" => $this->shipping->get_cart_total_breakdown(),
                    ]
                ])
            );
        } catch (Buckaroo_Paypal_Express_Exception $th) {
            wp_die(
                json_encode([
                    "error" => true,
                    "message" => $th->getMessage()
                ])
            );
        } catch (\Throwable $th) {
            Buckaroo_Logger::log(__METHOD__, $th->getMessage());
            wp_die(
                json_encode([
                    "error" => true,
                    "message" => 'Internal buckaroo error'
                ])
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
        header('Content-Type: application/json');
        try {
            if ($this->on_product_page()) {
                $this->cart->store_current();
                $this->shipping->create_cart_for_product_page();
            }

            $total = WC()->cart->get_total(false);

            if ($this->on_product_page()) {
                $this->cart->restore();
            }

            wp_die(
                json_encode([
                    "error" => false,
                    "data" => [
                        "total" => number_format($total, 2),
                    ]
                ])
            );
            
        } catch (\Throwable $th) {
            Buckaroo_Logger::log(__METHOD__, $th->getMessage());
            wp_die(
                json_encode([
                    "error"=>true,
                    "message" => "Cannot calculate cart total"
                ])
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
        header('Content-Type: application/json');
        if (!isset($_POST['orderId'])) {
           wp_die(
               json_encode([
                   "error"=>true,
                   "message" => "No paypal express order id provided"
               ])
            );
        }
        try {
            $response = $this->order->create_and_send($_POST['orderId']);
            
            if ($response === null) {
                $this->display_any_notices();
            }

            wp_die(
                json_encode([
                    "error"=>false,
                    "data" => $response
                ])
            );
        } catch (\Throwable $th) {
            Buckaroo_Logger::log(__METHOD__, $th->getMessage());
            wp_die(
                json_encode([
                    "error"=>true,
                    "message" => "Cannot process buckaroo payment"
                ])
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

        if (count($notices)) {
            wp_die(
                json_encode([
                "error"=>true,
                "message" => implode("</br>", $notices)
                ])
            );
        }
    }
    /**
     * Check if on product page
     *
     * @return boolean
     */
    protected function on_product_page()
    {
        return isset($_POST['page']) && $_POST['page'] === self::LOCATION_PRODUCT;
    }

    /**
     * Check if button is active on page
     *
     * @param string $page
     *
     * @return boolean
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
