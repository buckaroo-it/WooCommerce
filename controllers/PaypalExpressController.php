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
     * Cart content for restoring cart in product page
     *
     * @var array
     */
    protected $cart = null;

    public function __construct()
    {
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
        $this->enqueue_sdk();
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
     * enqueue buckaroo sdk
     *
     * @return void
     */
    protected function enqueue_sdk()
    {
        $path = "https://testcheckout.buckaroo.nl/api/buckaroosdk/script";
        if ($this->settings['mode'] === 'live') {
            $path = "https://checkout.buckaroo.nl/api/buckaroosdk/script";
        }
        wp_enqueue_script(
            'buckaroo_sdk',
            $path,
            array('jquery'),
            BuckarooConfig::VERSION
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
            if ($this->onProductPage()) {
                $this->create_cart_for_product_page();
            }
            wp_die(
                json_encode([
                    "error" => false,
                    "data" => [
                        "value" => $this->get_cart_total_breakdown(),
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
                    "message" => 'Interval buckaroo error'
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
            if ($this->onProductPage()) {
                $this->store_current_cart();
                $this->create_cart_for_product_page();
            }

            $total = WC()->cart->get_total(false);

            if ($this->onProductPage()) {
                $this->restore_cart();
            }

            wp_die(
                json_encode([
                    "error" => false,
                    "data" => [
                        "total" => $total,
                    ]
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
            wp_die(
                json_encode([
                    "error"=>false,
                    "data" => $this->create_and_send_order($_POST['orderId'])
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
     * Check if on product page
     *
     * @return boolean
     */
    protected function onProductPage()
    {
        return isset($_POST['page']) && $_POST['page'] === self::LOCATION_PRODUCT;
    }
    /**
     * Create new cart if button was pressed in product page
     *
     * @return void
     */
    protected function create_cart_for_product_page()
    {
        $order_data = $this->get_order_data();
        $cart = WC()->cart;

        $cart->empty_cart();

        $cart->add_to_cart(
            $this->get_product_id($order_data),
            $this->get_required_value($order_data, 'quantity')
        );
        $this->apply_paypal_fee($cart);
    }
    /**
     * Set cart data to be restored 
     *
     * @return void
     */
    public function store_current_cart()
    {
        $cart = WC()->cart;

        $this->cart = [
            "cart_contents" => $cart->get_cart_contents(),
            "applied_coupons" => $cart->get_applied_coupons(),
            "removed_cart_contents" => $cart->get_removed_cart_contents(),
        ];
    }
    /**
     * Restore cart
     *
     * @return void
     */
    protected function restore_cart()
    {
        if($this->cart !== null) {
            $cart = WC()->cart;
            $cart->empty_cart();
            $cart->set_cart_contents($this->cart['cart_contents']);
            $cart->set_applied_coupons($this->cart['applied_coupons']);
            $cart->set_removed_cart_contents($this->cart['removed_cart_contents']);
            $cart->calculate_totals();
        }
    }
    /**
     * Apply payment fee on cart
     *
     * @return void
     */
    public function apply_paypal_fee($cart)
    {
        WC()->session->set('chosen_payment_method', 'buckaroo_paypal');
        $cart->calculate_totals();


        do_action(
            'buckaroo_cart_calculate_fees',
            $cart,
            $this->settings['extrachargeamount'] ?? 0,
            $this->settings['feetax'] ?? ''
        );

        $this->store_fee_for_order($cart);
    }
    /**
     * Store the fee result in session to use in order
     *
     * @param WC_Cart $cart
     *
     * @return void
     */
    protected function store_fee_for_order($cart)
    {
        $fee = null;
        if (isset($cart->get_fees()["payment-fee"])) {
            $fee = $cart->get_fees()["payment-fee"];
        }
        WC()->session->set('buckaroo_paypal_fee', $fee);
    }
    /**
     * Get cart total brakdown by items, shipping & tax
     *
     * @param WC_Cart $cart
     *
     * @return array
     */
    protected function get_cart_total_breakdown()
    {

        $address_data = $this->get_address_data();

        WC()->customer->set_shipping_location(
            $this->get_required_value($address_data, 'country_code'),
            $this->get_required_value($address_data, 'state'),
            $this->get_required_value($address_data, 'postal_code'),
            $this->get_required_value($address_data, 'city'),
        );

        $cart = WC()->cart;
        $this->apply_paypal_fee($cart);

        WC()->cart->calculate_shipping();

        $total = $cart->get_total(false);
        $tax_total = $cart->get_total_tax();
        $shipping = $cart->get_shipping_total();
        $item_total = $total - $tax_total - $shipping;
        $currency = get_woocommerce_currency();


        return [
            "breakdown" => [
                "item_total" => [
                    "currency_code" => $currency,
                    "value" => $item_total
                ],
                "shipping" => [
                    "currency_code" => $currency,
                    "value" => $shipping
                ],
                "tax_total" => [
                    "currency_code" => $currency,
                    "value" => $tax_total
                ]
            ],
            "currency_code" => $currency,
            "value" => $total
        ];
    }
    /**
     * Get product id for simple and variable product
     *
     * @param array $order_data
     *
     * @return void
     */
    protected function get_product_id($order_data)
    {
        $variation_id = $this->get_value($order_data, 'variation_id');

        if (!empty($variation_id) || $variation_id != 0) {
            return $variation_id;
        }
        return $this->get_required_value($order_data, 'add-to-cart');
    }
    /**
     * Get required values or throw exception
     *
     * @param array $data
     * @param string $key
     *
     * @return mixed
     * @throws Exception
     */
    protected function get_required_value($data, $key)
    {
        if (!isset($data[$key])) {
            throw new Buckaroo_Paypal_Express_Exception("Field is required " . $key);
        }
        return $this->get_value($data, $key);
    }
    /**
     * Get value from array with a default
     *
     * @param array $data
     * @param string $key
     * @param mixed $default
     *
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
        if (!isset($_POST['shipping_data']) && !isset($_POST['shipping_data']['shipping_address'])) {
            throw new Buckaroo_Paypal_Express_Exception("Shipping address is required");
        }
        return $_POST['shipping_data']['shipping_address'];
    }
    /**
     * Get formatted order data from frontend
     *
     * @return array
     */
    protected function get_order_data()
    {
        if (!isset($_POST['order_data']) || count($_POST['order_data']) === 0) {
            throw new Buckaroo_Paypal_Express_Exception("Empty cart, cannot create order");
        }
        $request = [];
        foreach ($_POST['order_data'] as $data) {
            if (!isset($data['name']) || !isset($data['value'])) {
                throw new Buckaroo_Paypal_Express_Exception("Invalid data format");
            }
            $request[$data['name']] = $data['value'];
        }
        return $request;
    }

    /**
     * Create order from cart and send it to buckaroo
     *
     * @return WC_Order $order
     */
    protected function create_and_send_order($paypal_order_id)
    {
        $payment_method_id = "buckaroo_paypal";
        
        $customer = WC()->customer;
        $order_id = WC()->checkout()->create_order(array());

        $order = new WC_Order($order_id);

        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $payment_method = $available_gateways[$payment_method_id];

        $order->set_payment_method($payment_method);
        $order->set_address($customer->get_billing());
        $order->set_address($customer->get_shipping(), 'shipping');
        
        $order = $this->set_fee_on_order(
            $order,
            WC()->session->get('buckaroo_paypal_fee')
        );

        $order->save();
        
        if (method_exists($payment_method, 'set_express_order_id')) {
           $payment_method->set_express_order_id($paypal_order_id);
        }

        return $payment_method->process_payment($order_id);
    }
    /**
     * Set fees on order
     *
     * @param Wc_Order $order
     * @param stdClass | null $fee
     *
     * @return Wc_Order $order
     */
    protected function set_fee_on_order($order, $fee)
    {
        if ($fee === null) {
            return $order;
        }
        // Get a new instance of the WC_Order_Item_Fee Object
        $item_fee = new WC_Order_Item_Fee();

        $item_fee->set_name($fee->name); 
        $item_fee->set_amount($fee->amount); 
        $item_fee->set_tax_class($fee->tax_class);
        $item_fee->set_tax_status($fee->taxable); 
        $item_fee->set_total($fee->total);

        // Calculating Fee taxes
        $item_fee->calculate_taxes($order->get_address('shipping'));

        
        // Add Fee item to the order
        $order->add_item($item_fee);

        return $order;
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
