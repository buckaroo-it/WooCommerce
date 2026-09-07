<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressOrder;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressShipping;

class Test_PaypalExpressCartIntegrity extends WP_UnitTestCase
{
    /** @var int[] */
    private $product_ids = [];

    /** @var int[] */
    private $order_ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! WC()->session) {
            WC()->initialize_session();
        }
        if (! WC()->customer) {
            WC()->customer = new WC_Customer(0, true);
        }
        if (! WC()->cart) {
            WC()->initialize_cart();
        }

        WC()->cart->empty_cart(false);
        wc_clear_notices();
        $_POST = [];
        $_REQUEST = [];
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        global $wpdb;

        WC()->cart->empty_cart(false);
        wc_clear_notices();
        $_POST = [];
        $_REQUEST = [];
        wp_set_current_user(0);

        foreach ($this->product_ids as $product_id) {
            wp_delete_post($product_id, true);
        }
        foreach ($this->order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->delete(true);
            }
        }
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_buckaroo_paypal_express_quote_').'%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_timeout_buckaroo_paypal_express_quote_').'%'
            )
        );

        parent::tearDown();
    }

    public function test_incomplete_paypal_product_probe_fails_without_changing_the_live_cart(): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            1,
            $variation_id,
            [
                'attribute_style' => 'Classic',
                'attribute_shoe-size' => '41',
            ],
            ['third_party_data' => ['source' => 'live cart']]
        );
        $this->assertIsString($cart_item_key);
        wc_add_notice('Keep this notice', 'notice');
        $state_before = $this->captureLiveState();

        $this->setPaypalRequest(
            [
                'attribute_style' => 'Classic',
                'attribute_shoe-size' => '',
                'quantity' => '1',
                'add-to-cart' => (string) $product_id,
                'product_id' => (string) $product_id,
                'variation_id' => '0',
            ],
            'express-cart-totals',
            'cart_total_nonce'
        );

        $response = $this->captureJsonResponse([$this->controller(), 'get_cart_total']);

        $this->assertTrue($response['error']);
        $this->assertSame($state_before, $this->captureLiveState());
    }

    public function test_valid_paypal_wildcard_probe_returns_total_without_changing_the_live_cart(): void
    {
        $live_product_id = $this->createSimpleProduct('Keep in cart', '15.00');
        $live_cart_key = WC()->cart->add_to_cart(
            $live_product_id,
            1,
            0,
            [],
            ['third_party_data' => ['source' => 'live cart']]
        );
        $this->assertIsString($live_cart_key);
        wc_add_notice('Keep this notice', 'notice');
        $state_before = $this->captureLiveState();

        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $this->setPaypalRequest(
            [
                'attribute_style' => 'Classic',
                'attribute_shoe-size' => '42',
                'quantity' => '1',
                'add-to-cart' => (string) $product_id,
                'product_id' => (string) $product_id,
                'variation_id' => (string) $variation_id,
            ],
            'express-cart-totals',
            'cart_total_nonce'
        );

        $response = $this->captureJsonResponse([$this->controller(), 'get_cart_total']);

        $this->assertFalse($response['error']);
        $this->assertSame('25.00', $response['data']['total']);
        $this->assertSame($state_before, $this->captureLiveState());
    }

    public function test_paypal_quote_formats_totals_over_one_thousand_for_the_api(): void
    {
        $product_id = $this->createSimpleProduct('Expensive cart product', '1234.00');
        $this->assertIsString(WC()->cart->add_to_cart($product_id, 1));
        $this->setPaypalRequest([], 'express-cart-totals', 'cart_total_nonce', 'cart');

        $response = $this->captureJsonResponse([$this->controller(), 'get_cart_total']);

        $this->assertFalse($response['error']);
        $this->assertSame('1234.00', $response['data']['total']);
        $this->assertNotEmpty($response['data']['quote_token']);
    }

    public function test_paypal_shipping_probe_uses_wildcard_attributes_without_changing_live_state(): void
    {
        $live_product_id = $this->createSimpleProduct('Keep in cart', '15.00');
        $live_cart_key = WC()->cart->add_to_cart(
            $live_product_id,
            1,
            0,
            [],
            ['third_party_data' => ['source' => 'live cart']]
        );
        $this->assertIsString($live_cart_key);
        WC()->customer->set_shipping_location('BE', '', '1000', 'Brussels');
        WC()->session->set('chosen_payment_method', 'cod');
        wc_add_notice('Keep this notice', 'notice');
        $state_before = $this->captureLiveState();

        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $this->setPaypalRequest(
            [
                'attribute_style' => 'Classic',
                'attribute_shoe-size' => '42',
                'quantity' => '1',
                'add-to-cart' => (string) $product_id,
                'product_id' => (string) $product_id,
                'variation_id' => (string) $variation_id,
            ],
            'express-set-shipping',
            'set_shipping_nonce'
        );
        $_POST['shipping_data'] = [
            'shipping_address' => [
                'country_code' => 'NL',
                'state' => '',
                'postal_code' => '8441ER',
                'city' => 'Heerenveen',
            ],
        ];
        $_REQUEST = $_POST;

        $response = $this->captureJsonResponse([$this->controller(), 'add_shipping']);

        $this->assertFalse($response['error']);
        $this->assertSame('25.00', $response['data']['value']['value']);
        $this->assertSame($state_before, $this->captureLiveState());
    }

    public function test_approved_paypal_product_creation_uses_selected_wildcard_attributes(): void
    {
        $live_product_id = $this->createSimpleProduct('Replace after approval', '15.00');
        $this->assertIsString(WC()->cart->add_to_cart($live_product_id, 1));
        WC()->cart->add_fee('Old cart fee', 5);
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();

        $this->setPaypalRequest(
            [
                'attribute_style' => 'Classic',
                'attribute_shoe-size' => '42',
                'quantity' => '1',
                'add-to-cart' => (string) $product_id,
                'product_id' => (string) $product_id,
                'variation_id' => (string) $variation_id,
            ],
            'express-send_order',
            'send_order_nonce'
        );
        $state_before = $this->captureLiveState();

        $order_cart = (new PaypalExpressShipping())->with_cart_for_order(
            true,
            static function ($cart): array {
                $order_cart = [
                    'contents' => array_values($cart->get_cart_contents()),
                    'total' => $cart->get_total(false),
                    'fees' => $cart->get_fees(),
                ];
                $cart->empty_cart(false);

                return $order_cart;
            }
        );

        $this->assertCount(1, $order_cart['contents']);
        $this->assertSame($product_id, $order_cart['contents'][0]['product_id']);
        $this->assertSame($variation_id, $order_cart['contents'][0]['variation_id']);
        $this->assertSame(
            [
                'attribute_style' => 'Classic',
                'attribute_shoe-size' => '42',
            ],
            $order_cart['contents'][0]['variation']
        );
        $this->assertSame('25.00', $order_cart['total']);
        $this->assertArrayNotHasKey('old-cart-fee', $order_cart['fees']);
        $this->assertSame($state_before, $this->captureLiveState());
    }

    public function test_failed_paypal_order_restores_original_cart_customer_session_and_notices(): void
    {
        $product_id = $this->createSimpleProduct('Keep after failure', '15.00');
        $this->assertIsString(
            WC()->cart->add_to_cart(
                $product_id,
                1,
                0,
                [],
                ['third_party_data' => ['source' => 'live cart']]
            )
        );
        WC()->customer->set_shipping_location('BE', '', '1000', 'Brussels');
        WC()->session->set('chosen_payment_method', 'cod');
        wc_add_notice('Keep this notice', 'notice');
        $state_before = $this->captureLiveState();
        [$order_product_id, $variation_id] = $this->createWildcardVariationProduct();
        $this->setPaypalRequest(
            [
                'attribute_style' => 'Classic',
                'attribute_shoe-size' => '42',
                'quantity' => '1',
                'add-to-cart' => (string) $order_product_id,
                'product_id' => (string) $order_product_id,
                'variation_id' => (string) $variation_id,
            ],
            'express-set-shipping',
            'set_shipping_nonce'
        );
        $_POST['shipping_data'] = [
            'shipping_address' => [
                'country_code' => 'NL',
                'state' => '',
                'postal_code' => '8441ER',
                'city' => 'Heerenveen',
            ],
        ];
        $_REQUEST = $_POST;
        $quote_response = $this->captureJsonResponse([$this->controller(), 'add_shipping']);
        $this->assertFalse($quote_response['error']);
        $_POST['orderId'] = 'PAYPAL-ORDER';
        $_POST['quote_token'] = $quote_response['data']['quote_token'];
        $_POST['send_order_nonce'] = wp_create_nonce('express-send_order');
        $_REQUEST = $_POST;
        $order = new class {
            public function create_and_send($order_id): array
            {
                throw new RuntimeException('Gateway failed');
            }
        };
        $controller = new PaypalExpressController(new PaypalExpressShipping(), $order);

        $response = $this->captureJsonResponse([$controller, 'send_order']);

        $this->assertTrue($response['error']);
        $this->assertSame($state_before, $this->captureLiveState());
    }

    public function test_paypal_rejects_product_state_changed_after_the_quote(): void
    {
        $live_product_id = $this->createSimpleProduct('Keep after stale quote', '15.00');
        $this->assertIsString(WC()->cart->add_to_cart($live_product_id, 1));
        $state_before = $this->captureLiveState();
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $fields = [
            'attribute_style' => 'Classic',
            'attribute_shoe-size' => '42',
            'quantity' => '1',
            'add-to-cart' => (string) $product_id,
            'product_id' => (string) $product_id,
            'variation_id' => (string) $variation_id,
        ];
        $this->setPaypalRequest($fields, 'express-cart-totals', 'cart_total_nonce');
        $quote_response = $this->captureJsonResponse([$this->controller(), 'get_cart_total']);
        $this->assertFalse($quote_response['error']);

        $fields['attribute_shoe-size'] = '41';
        $this->setPaypalRequest($fields, 'express-send_order', 'send_order_nonce');
        $_POST['orderId'] = 'PAYPAL-ORDER';
        $_POST['quote_token'] = $quote_response['data']['quote_token'];
        $_REQUEST = $_POST;
        $calls = 0;
        $order = new class($calls) {
            private $calls;

            public function __construct(int &$calls)
            {
                $this->calls = &$calls;
            }

            public function create_and_send($order_id): array
            {
                $this->calls++;

                return ['redirect' => '/order-received'];
            }
        };
        $controller = new PaypalExpressController(new PaypalExpressShipping(), $order);

        $response = $this->captureJsonResponse([$controller, 'send_order']);

        $this->assertTrue($response['error']);
        $this->assertSame(0, $calls);
        $this->assertSame($state_before, $this->captureLiveState());
    }

    public function test_identical_paypal_quotes_are_unique_and_each_can_only_be_used_once(): void
    {
        $product_id = $this->createSimpleProduct('Single use quote product', '15.00');
        $this->assertIsString(WC()->cart->add_to_cart($product_id, 1));
        $this->setPaypalRequest([], 'express-cart-totals', 'cart_total_nonce', 'cart');
        $quote_response = $this->captureJsonResponse([$this->controller(), 'get_cart_total']);
        $second_quote_response = $this->captureJsonResponse([$this->controller(), 'get_cart_total']);
        $this->assertFalse($quote_response['error']);
        $this->assertFalse($second_quote_response['error']);
        $this->assertNotSame(
            $quote_response['data']['quote_token'],
            $second_quote_response['data']['quote_token']
        );

        $_POST = [
            'page' => 'cart',
            'orderId' => 'PAYPAL-ORDER',
            'quote_token' => $quote_response['data']['quote_token'],
            'send_order_nonce' => wp_create_nonce('express-send_order'),
        ];
        $_REQUEST = $_POST;
        $calls = 0;
        $order = new class($calls) {
            private $calls;

            public function __construct(int &$calls)
            {
                $this->calls = &$calls;
            }

            public function create_and_send($order_id): array
            {
                $this->calls++;

                return ['redirect' => '/order-received'];
            }
        };
        $controller = new PaypalExpressController(new PaypalExpressShipping(), $order);

        $first = $this->captureJsonResponse([$controller, 'send_order']);
        $_POST['quote_token'] = $second_quote_response['data']['quote_token'];
        $_REQUEST = $_POST;
        $second = $this->captureJsonResponse([$controller, 'send_order']);
        $_POST['quote_token'] = $quote_response['data']['quote_token'];
        $_REQUEST = $_POST;
        $replay = $this->captureJsonResponse([$controller, 'send_order']);

        $this->assertFalse($first['error']);
        $this->assertFalse($second['error']);
        $this->assertTrue($replay['error']);
        $this->assertSame(2, $calls);
    }

    public function test_successful_cart_payment_propagates_cart_emptying_to_the_live_cart(): void
    {
        $product_id = $this->createSimpleProduct('Paid cart product', '15.00');
        $this->assertIsString(WC()->cart->add_to_cart($product_id, 1));

        (new PaypalExpressShipping())->with_cart_for_order(
            false,
            static function ($cart): array {
                $cart->empty_cart(false);

                return ['redirect' => '/order-received'];
            }
        );

        $this->assertTrue(WC()->cart->is_empty());
    }

    public function test_approved_paypal_cart_order_uses_the_quoted_address_total_and_fee_once(): void
    {
        $product_id = $this->createSimpleProduct('Cart order product', '15.00');
        $this->assertIsString(WC()->cart->add_to_cart($product_id, 1));
        WC()->customer->set_billing_location('BE', '', '1000', 'Brussels');
        WC()->customer->set_shipping_location('BE', '', '1000', 'Brussels');
        WC()->session->set('chosen_payment_method', 'cod');
        $state_before = $this->captureLiveState();

        $gateway = new class extends WC_Payment_Gateway {
            public $processed_order_id;

            public function __construct()
            {
                $this->id = 'buckaroo_paypal';
                $this->enabled = 'yes';
                $this->title = 'PayPal';
            }

            public function set_express_order_id($order_id): void
            {
            }

            public function process_payment($order_id): array
            {
                $this->processed_order_id = $order_id;

                return [
                    'result' => 'success',
                    'redirect' => '/order-received/'.$order_id,
                ];
            }
        };
        $gateways_before = WC()->payment_gateways->payment_gateways;
        WC()->payment_gateways->payment_gateways[] = $gateway;
        $settings_before = get_option('woocommerce_buckaroo_paypal_settings', null);
        update_option(
            'woocommerce_buckaroo_paypal_settings',
            [
                'enabled' => 'yes',
                'express' => ['cart'],
                'extrachargeamount' => '2.50',
                'feetax' => '',
            ]
        );
        $address_fee = static function ($cart): void {
            if (WC()->customer->get_shipping_country() === 'NL') {
                $cart->add_fee('NL delivery', 7);
            }
        };
        add_action('woocommerce_cart_calculate_fees', $address_fee, 20);

        $this->setPaypalRequest([], 'express-set-shipping', 'set_shipping_nonce', 'cart');
        $_POST['shipping_data'] = [
            'shipping_address' => [
                'country_code' => 'NL',
                'state' => '',
                'postal_code' => '8441ER',
                'city' => 'Heerenveen',
            ],
        ];
        $_REQUEST = $_POST;

        try {
            $quote_response = $this->captureJsonResponse([$this->controller(), 'add_shipping']);
            $this->assertFalse($quote_response['error']);
            $_POST['orderId'] = 'PAYPAL-ORDER';
            $_POST['quote_token'] = $quote_response['data']['quote_token'];
            $_POST['send_order_nonce'] = wp_create_nonce('express-send_order');
            $_REQUEST = $_POST;
            $response = $this->captureJsonResponse([$this->controller(), 'send_order']);
        } finally {
            remove_action('woocommerce_cart_calculate_fees', $address_fee, 20);
            WC()->payment_gateways->payment_gateways = $gateways_before;
            if ($settings_before === null) {
                delete_option('woocommerce_buckaroo_paypal_settings');
            } else {
                update_option('woocommerce_buckaroo_paypal_settings', $settings_before);
            }
        }

        $this->assertFalse($response['error']);
        $this->assertSame('success', $response['data']['result']);
        $this->assertIsInt($gateway->processed_order_id);
        $this->order_ids[] = $gateway->processed_order_id;
        $order = wc_get_order($gateway->processed_order_id);
        $this->assertInstanceOf(WC_Order::class, $order);
        $this->assertSame('BE', $order->get_billing_country());
        $this->assertSame('NL', $order->get_shipping_country());
        $this->assertSame('24.50', $order->get_total());
        $fees = $order->get_items('fee');
        $this->assertCount(2, $fees);
        $this->assertCount(
            1,
            array_filter(
                $fees,
                static function ($fee): bool {
                    return $fee->get_name() === 'Payment fee';
                }
            )
        );
        $this->assertSame($state_before, $this->captureLiveState());
    }

    public function test_paypal_processing_exception_marks_the_created_order_failed(): void
    {
        $product_id = $this->createSimpleProduct('Failed order product', '15.00');
        $this->assertIsString(WC()->cart->add_to_cart($product_id, 1));
        $state_before = $this->captureLiveState();
        $gateway = new class extends WC_Payment_Gateway {
            public $processed_order_id;

            public function __construct()
            {
                $this->id = 'buckaroo_paypal';
                $this->enabled = 'yes';
                $this->title = 'PayPal';
            }

            public function process_payment($order_id): array
            {
                $this->processed_order_id = $order_id;

                throw new RuntimeException('Gateway processing failed');
            }
        };
        $gateways_before = WC()->payment_gateways->payment_gateways;
        WC()->payment_gateways->payment_gateways[] = $gateway;
        $exception = null;

        try {
            (new PaypalExpressShipping())->with_cart_for_order(
                false,
                static function (): array {
                    return (new PaypalExpressOrder())->create_and_send('PAYPAL-ORDER');
                }
            );
        } catch (RuntimeException $th) {
            $exception = $th;
        } finally {
            WC()->payment_gateways->payment_gateways = $gateways_before;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertIsInt($gateway->processed_order_id);
        $this->order_ids[] = $gateway->processed_order_id;
        $order = wc_get_order($gateway->processed_order_id);
        $this->assertInstanceOf(WC_Order::class, $order);
        $this->assertTrue($order->has_status('failed'));
        $this->assertSame($state_before, $this->captureLiveState());
    }

    public function test_repeated_paypal_cart_probes_preserve_live_and_persistent_state(): void
    {
        $user_id = self::factory()->user->create();
        wp_set_current_user($user_id);
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            1,
            $variation_id,
            [
                'attribute_style' => 'Classic',
                'attribute_shoe-size' => '41',
            ],
            ['third_party_data' => ['source' => 'live cart']]
        );
        $this->assertIsString($cart_item_key);
        WC()->customer->set_shipping_location('BE', '', '1000', 'Brussels');
        WC()->session->set('chosen_payment_method', 'cod');
        wc_add_notice('Keep this notice', 'notice');
        $persistent_key = '_woocommerce_persistent_cart_'.get_current_blog_id();
        $persistent_before = [
            'sentinel' => 'keep me',
            'cart' => ['original' => ['third_party_data' => ['nested' => true]]],
        ];
        update_user_meta($user_id, $persistent_key, $persistent_before);
        $state_before = $this->captureLiveState();

        $this->setPaypalRequest([], 'express-cart-totals', 'cart_total_nonce', 'cart');
        $first_total = $this->captureJsonResponse([$this->controller(), 'get_cart_total']);
        $this->setPaypalRequest([], 'express-cart-totals', 'cart_total_nonce', 'cart');
        $second_total = $this->captureJsonResponse([$this->controller(), 'get_cart_total']);

        $this->setPaypalRequest([], 'express-set-shipping', 'set_shipping_nonce', 'cart');
        $_POST['shipping_data'] = [
            'shipping_address' => [
                'country_code' => 'NL',
                'state' => '',
                'postal_code' => '8441ER',
                'city' => 'Heerenveen',
            ],
        ];
        $_REQUEST = $_POST;
        $shipping = $this->captureJsonResponse([$this->controller(), 'add_shipping']);

        $this->assertFalse($first_total['error']);
        $this->assertFalse($second_total['error']);
        $this->assertSame($first_total['data']['total'], $second_total['data']['total']);
        $this->assertNotSame(
            $first_total['data']['quote_token'],
            $second_total['data']['quote_token']
        );
        $this->assertSame('25.00', $shipping['data']['value']['value']);
        $this->assertSame($state_before, $this->captureLiveState());
        $this->assertSame($persistent_before, get_user_meta($user_id, $persistent_key, true));
    }

    private function controller(): PaypalExpressController
    {
        return new PaypalExpressController(
            new PaypalExpressShipping(),
            new PaypalExpressOrder()
        );
    }

    private function createWildcardVariationProduct(): array
    {
        $style = new WC_Product_Attribute();
        $style->set_name('Style');
        $style->set_options(['Classic']);
        $style->set_visible(true);
        $style->set_variation(true);

        $size = new WC_Product_Attribute();
        $size->set_name('Shoe size');
        $size->set_options(['41', '42']);
        $size->set_visible(true);
        $size->set_variation(true);

        $product = new WC_Product_Variable();
        $product->set_name('PayPal wildcard shoes');
        $product->set_status('publish');
        $product->set_attributes([$style, $size]);
        $product_id = $product->save();

        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product_id);
        $variation->set_status('publish');
        $variation->set_regular_price('25.00');
        $variation->set_price('25.00');
        $variation->set_attributes(['style' => 'Classic', 'shoe-size' => '']);
        $variation_id = $variation->save();

        $this->product_ids[] = $variation_id;
        $this->product_ids[] = $product_id;

        return [$product_id, $variation_id];
    }

    private function createSimpleProduct(string $name, string $price): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_status('publish');
        $product->set_regular_price($price);
        $product->set_price($price);
        $product_id = $product->save();
        $this->product_ids[] = $product_id;

        return $product_id;
    }

    private function setPaypalRequest(
        array $fields,
        string $nonce_action,
        string $nonce_key,
        string $page = 'product'
    ): void
    {
        $_POST = [
            'page' => $page,
            'order_data' => array_map(
                static function ($name, $value): array {
                    return ['name' => $name, 'value' => $value];
                },
                array_keys($fields),
                array_values($fields)
            ),
            $nonce_key => wp_create_nonce($nonce_action),
        ];
        $_REQUEST = $_POST;
    }

    private function captureJsonResponse(callable $endpoint): array
    {
        $die_handler = static function () {};
        $handler_filter = static function () use ($die_handler) {
            return $die_handler;
        };

        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', $handler_filter);
        ob_start();

        try {
            call_user_func($endpoint);
        } finally {
            remove_filter('wp_die_ajax_handler', $handler_filter);
            remove_filter('wp_doing_ajax', '__return_true');
        }

        $response = json_decode((string) ob_get_clean(), true);
        $this->assertIsArray($response);

        return $response;
    }

    private function captureLiveState(): array
    {
        $session_data = new ReflectionProperty(WC_Session::class, '_data');
        if (PHP_VERSION_ID < 80100) {
            $session_data->setAccessible(true);
        }

        return [
            'cart_object' => WC()->cart,
            'cart_contents' => serialize(WC()->cart->get_cart_contents()),
            'removed_cart_contents' => serialize(WC()->cart->get_removed_cart_contents()),
            'coupons' => WC()->cart->get_applied_coupons(),
            'fees' => serialize(WC()->cart->get_fees()),
            'totals' => WC()->cart->get_totals(),
            'notices' => wc_get_notices(),
            'customer_object' => WC()->customer,
            'customer' => WC()->customer->get_data(),
            'session_object' => WC()->session,
            'session_data' => $session_data->getValue(WC()->session),
            'shipping_packages' => WC()->shipping()->packages,
            'persistent_cart' => get_current_user_id()
                ? get_user_meta(
                    get_current_user_id(),
                    '_woocommerce_persistent_cart_'.get_current_blog_id(),
                    true
                )
                : null,
        ];
    }
}
