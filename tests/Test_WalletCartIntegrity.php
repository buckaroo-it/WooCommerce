<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayController;
use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayController;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;

class Test_WalletCartIntegrity extends WP_UnitTestCase
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
        $_GET = [];
    }

    protected function tearDown(): void
    {
        WC()->cart->empty_cart(false);
        wc_clear_notices();
        $_GET = [];
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

        parent::tearDown();
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_wallet_wildcard_variation_probe_preserves_the_selected_cart_line(
        string $controller,
        string $wallet_name
    ): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $variation = ['attribute_shoe-size' => '42'];
        $cart_item_data = [
            'third_party_data' => [
                'engraving' => 'keep me',
            ],
        ];

        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            1,
            $variation_id,
            $variation,
            $cart_item_data
        );

        $this->assertIsString($cart_item_key, 'The reported wildcard variation fixture must be valid.');
        $cart_before = WC()->cart->get_cart_contents();

        $_GET = [
            'product_id' => (string) $product_id,
            'variation_id' => (string) $variation_id,
            'quantity' => '1',
            'attributes' => $variation,
        ];

        $items = $this->captureJsonResponse([$controller, 'getItemsFromDetailPage']);

        $this->assertCount(1, $items);
        $this->assertSame($variation_id, $items[0]['id']);
        $this->assertSame($variation, $items[0]['attributes']);
        $this->assertSame($cart_before, WC()->cart->get_cart_contents());
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_wallet_shipping_probe_restores_all_live_checkout_state(
        string $controller,
        string $wallet_name
    ): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $variation = ['attribute_shoe-size' => '42'];
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            1,
            $variation_id,
            $variation,
            ['third_party_data' => ['engraving' => 'keep me']]
        );
        $this->assertIsString($cart_item_key);

        WC()->customer->set_shipping_location('NL', 'NH', '1017GB', 'Amsterdam');
        WC()->session->set('chosen_payment_method', 'cod');
        WC()->session->set('chosen_shipping_methods', ['flat_rate:1']);
        WC()->session->set('wallet_probe_guard', ['nested' => 'keep me']);
        $shipping_enabled_before = WC()->shipping()->enabled;
        $shipping_method_count_before = get_transient('wc_shipping_method_count');
        WC()->shipping()->enabled = true;
        set_transient(
            'wc_shipping_method_count',
            [
                'version' => WC_Cache_Helper::get_transient_version('shipping'),
                'legacy' => 0,
                'enabled' => 1,
                'disabled' => 0,
            ]
        );
        WC()->shipping()->packages = [
            [
                'sentinel' => 'keep me',
                'rates' => [
                    'stale' => new WC_Shipping_Rate('stale', 'Stale rate', 99),
                ],
            ],
        ];
        wc_add_notice('Keep this notice', 'notice');

        $state_before = $this->captureLiveCheckoutState();

        $_GET = [
            'product_id' => (string) $product_id,
            'variation_id' => (string) $variation_id,
            'quantity' => '1',
            'country_code' => 'BE',
            'attributes' => $variation,
        ];

        $live_cart = WC()->cart;
        $calculated_countries = [];
        $capture_country = static function ($cart) use ($live_cart, &$calculated_countries) {
            if ($cart !== $live_cart) {
                $calculated_countries[] = WC()->customer->get_shipping_country();
            }
        };
        add_action('woocommerce_before_calculate_totals', $capture_country, PHP_INT_MAX);
        $provide_country_rate = static function (array $packages): array {
            foreach ($packages as &$package) {
                $country = $package['destination']['country'] ?? '';
                $rate_id = 'fresh-'.$country;
                $package['rates'] = [
                    $rate_id => new WC_Shipping_Rate($rate_id, "Fresh {$country}", 7),
                ];
            }
            unset($package);

            return $packages;
        };
        add_filter('woocommerce_shipping_packages', $provide_country_rate);

        try {
            $shipping_methods = $this->captureJsonResponse([$controller, 'getShippingMethods']);
        } finally {
            remove_filter('woocommerce_shipping_packages', $provide_country_rate);
            remove_action('woocommerce_before_calculate_totals', $capture_country, PHP_INT_MAX);
            WC()->shipping()->enabled = $shipping_enabled_before;
            if ($shipping_method_count_before === false) {
                delete_transient('wc_shipping_method_count');
            } else {
                set_transient('wc_shipping_method_count', $shipping_method_count_before);
            }
        }

        $this->assertSame(['BE'], $calculated_countries);
        $this->assertSame('fresh-BE', $shipping_methods[0]['identifier']);
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_wallet_reports_a_failed_isolated_add_without_touching_the_live_cart(
        string $controller,
        string $wallet_name
    ): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $variation = ['attribute_shoe-size' => '42'];
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id, $variation);
        $this->assertIsString($cart_item_key);
        $state_before = $this->captureLiveCheckoutState();

        $_GET = [
            'product_id' => (string) $product_id,
            'variation_id' => (string) $variation_id,
            'quantity' => '1',
            'attributes' => $variation,
        ];

        $reject_quantity = static function () {
            return 0;
        };
        add_filter('woocommerce_add_to_cart_quantity', $reject_quantity);

        try {
            $response = $this->captureJsonResponse([$controller, 'getItemsFromDetailPage']);
        } finally {
            remove_filter('woocommerce_add_to_cart_quantity', $reject_quantity);
        }

        $this->assertSame('fail', $response['status']);
        $this->assertSame("Unable to calculate {$wallet_name} cart.", $response['message']);
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_wallet_rejects_tampered_attributes_and_variation_relationships(
        string $controller,
        string $wallet_name
    ): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        [$other_product_id, $other_variation_id] = $this->createWildcardVariationProduct();
        $variation = ['attribute_shoe-size' => '42'];
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id, $variation);
        $this->assertIsString($cart_item_key);
        $state_before = $this->captureLiveCheckoutState();

        $requests = [
            [
                'product_id' => (string) $product_id,
                'variation_id' => (string) $other_variation_id,
                'quantity' => '1',
                'attributes' => $variation,
            ],
            [
                'product_id' => (string) $product_id,
                'variation_id' => (string) $variation_id,
                'quantity' => '1',
                'attributes' => ['attribute_tampered' => '42'],
            ],
            [
                'product_id' => (string) $other_product_id,
                'variation_id' => (string) $other_variation_id,
                'quantity' => '1',
                'attributes' => 'attribute_shoe-size=42',
            ],
        ];

        foreach ($requests as $request) {
            $_GET = $request;
            $response = $this->captureJsonResponse([$controller, 'getItemsFromDetailPage']);

            $this->assertSame('fail', $response['status']);
            $this->assertSame($state_before, $this->captureLiveCheckoutState());
        }
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_repeated_wallet_probes_preserve_persistent_and_complete_cart_state(
        string $controller,
        string $wallet_name
    ): void
    {
        $user_id = self::factory()->user->create();
        wp_set_current_user($user_id);

        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $variation = ['attribute_shoe-size' => '42'];
        $removed_product_id = $this->createSimpleProduct('Removed product', '10.00');
        $removed_key = WC()->cart->add_to_cart($removed_product_id, 1);
        $this->assertIsString($removed_key);
        WC()->cart->remove_cart_item($removed_key);

        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            1,
            $variation_id,
            $variation,
            ['third_party_data' => ['engraving' => 'keep me']]
        );
        $this->assertIsString($cart_item_key);

        $coupon = new WC_Coupon();
        $coupon->set_code('wallet-safe');
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount('5');
        $coupon_id = $coupon->save();
        $this->product_ids[] = $coupon_id;
        WC()->cart->apply_coupon('wallet-safe');
        WC()->cart->add_fee('Existing third-party fee', 2.5, true);

        WC()->session->set('chosen_payment_method', 'cod');
        WC()->session->set('wallet_probe_guard', ['nested' => 'keep me']);
        wc_add_notice('Keep this notice', 'notice');

        $persistent_key = '_woocommerce_persistent_cart_'.get_current_blog_id();
        $persistent_before = [
            'sentinel' => 'keep me',
            'cart' => ['original' => ['third_party_data' => ['nested' => true]]],
        ];
        update_user_meta($user_id, $persistent_key, $persistent_before);
        $state_before = $this->captureLiveCheckoutState();

        $_GET = [
            'product_id' => (string) $product_id,
            'variation_id' => (string) $variation_id,
            'quantity' => '1',
            'attributes' => $variation,
        ];

        $first_items = $this->captureJsonResponse([$controller, 'getItemsFromDetailPage']);
        $second_items = $this->captureJsonResponse([$controller, 'getItemsFromDetailPage']);

        $this->assertSame($first_items, $second_items);
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
        $this->assertSame($persistent_before, get_user_meta($user_id, $persistent_key, true));
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_wallet_restores_live_state_when_calculation_throws(
        string $controller,
        string $wallet_name
    ): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $variation = ['attribute_shoe-size' => '42'];
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id, $variation);
        $this->assertIsString($cart_item_key);
        $state_before = $this->captureLiveCheckoutState();

        $_GET = [
            'product_id' => (string) $product_id,
            'variation_id' => (string) $variation_id,
            'quantity' => '1',
            'attributes' => $variation,
        ];
        $throw = static function () {
            throw new RuntimeException('Calculation failed');
        };
        add_action('woocommerce_before_calculate_totals', $throw, PHP_INT_MAX);

        try {
            $response = $this->captureJsonResponse([$controller, 'getItemsFromDetailPage']);
        } finally {
            remove_action('woocommerce_before_calculate_totals', $throw, PHP_INT_MAX);
        }

        $this->assertSame('fail', $response['status']);
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_wallet_probe_keeps_required_third_party_add_to_cart_lines(
        string $controller,
        string $wallet_name
    ): void
    {
        $live_product_id = $this->createSimpleProduct('Keep in cart', '15.00');
        $live_cart_key = WC()->cart->add_to_cart($live_product_id, 1);
        $this->assertIsString($live_cart_key);
        $state_before = $this->captureLiveCheckoutState();

        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $child_product_id = $this->createSimpleProduct('Required bundle child', '5.00');
        $child_add_results = [];
        $add_required_child = static function ($cart_item_key, $added_product_id) use (
            $product_id,
            $child_product_id,
            &$child_add_results
        ) {
            if ((int) $added_product_id === $product_id) {
                $child_add_results[] = WC()->cart->add_to_cart($child_product_id, 1);
            }
        };
        add_action('woocommerce_add_to_cart', $add_required_child, 10, 2);

        $_GET = [
            'product_id' => (string) $product_id,
            'variation_id' => (string) $variation_id,
            'quantity' => '1',
            'attributes' => ['attribute_shoe-size' => '42'],
        ];

        try {
            $items = $this->captureJsonResponse([$controller, 'getItemsFromDetailPage']);
        } finally {
            remove_action('woocommerce_add_to_cart', $add_required_child, 10);
        }

        $this->assertNotContains(false, $child_add_results);
        $this->assertArrayNotHasKey('status', $items);
        $product_ids = array_column(
            array_values(array_filter($items, static function ($item) {
                return ($item['type'] ?? '') === 'product';
            })),
            'id'
        );
        sort($product_ids);
        $expected_ids = [$variation_id, $child_product_id];
        sort($expected_ids);

        $this->assertSame($expected_ids, $product_ids);
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_wallet_probe_includes_the_configured_express_payment_fee(
        string $controller,
        string $wallet_name
    ): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $option_name = $wallet_name === 'Apple Pay'
            ? 'woocommerce_buckaroo_applepay_settings'
            : 'woocommerce_buckaroo_googlepay_settings';
        $settings_before = get_option($option_name, null);
        update_option(
            $option_name,
            [
                'extrachargeamount' => '2.50',
                'feetax' => '',
            ]
        );

        $_GET = [
            'product_id' => (string) $product_id,
            'variation_id' => (string) $variation_id,
            'quantity' => '1',
            'attributes' => ['attribute_shoe-size' => '42'],
        ];

        try {
            $items = $this->captureJsonResponse([$controller, 'getItemsFromDetailPage']);
        } finally {
            if ($settings_before === null) {
                delete_option($option_name);
            } else {
                update_option($option_name, $settings_before);
            }
        }

        $fees = array_values(array_filter($items, static function ($item) {
            return ($item['type'] ?? '') === 'fee';
        }));
        $this->assertCount(1, $fees);
        $this->assertSame(2.5, $fees[0]['price']);
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_current_cart_wallet_calculations_preserve_checkout_state(
        string $controller,
        string $wallet_name
    ): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            1,
            $variation_id,
            ['attribute_shoe-size' => '42'],
            ['third_party_data' => ['source' => 'live cart']]
        );
        $this->assertIsString($cart_item_key);
        WC()->customer->set_shipping_location('NL', 'NH', '1017GB', 'Amsterdam');
        WC()->session->set('chosen_payment_method', 'cod');
        WC()->session->set('chosen_shipping_methods', ['flat_rate:1']);
        wc_add_notice('Keep this notice', 'notice');
        $state_before = $this->captureLiveCheckoutState();

        $_GET = [];
        $this->captureJsonResponse([$controller, 'getItemsFromCart']);
        $this->captureJsonResponse([$controller, 'getCartTotal']);
        $_GET = ['country_code' => 'BE'];
        $this->captureJsonResponse([$controller, 'getShippingMethods']);

        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    public function walletControllers(): array
    {
        return [
            'Apple Pay' => [ApplepayController::class, 'Apple Pay'],
            'Google Pay' => [GooglepayController::class, 'Google Pay'],
        ];
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_order_uses_selected_wildcard_attributes_without_rebuilding_the_live_cart(
        string $gateway_class
    ): void
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
        WC()->customer->set_shipping_country('NL');
        $state_before = $this->captureLiveCheckoutState();

        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $items = [
            [
                'type' => 'product',
                'id' => $variation_id,
                'name' => 'Wildcard shoes',
                'price' => 25,
                'quantity' => 1,
                'attributes' => ['attribute_shoe-size' => '42'],
            ],
        ];
        $address = [
            'givenName' => 'Jane',
            'familyName' => 'Doe',
            'emailAddress' => 'jane@example.com',
            'phoneNumber' => '+31612345678',
            'addressLines' => ['Kerkstraat 42'],
            'locality' => 'Amsterdam',
            'administrativeArea' => 'NH',
            'postalCode' => '1017GB',
            'countryCode' => 'BE',
        ];
        $billing_address = $address;
        $billing_address['countryCode'] = 'DE';

        $live_cart = WC()->cart;
        $calculated_countries = [];
        $capture_country = static function ($cart) use ($live_cart, &$calculated_countries) {
            if ($cart !== $live_cart) {
                $calculated_countries[] = [
                    'billing' => WC()->customer->get_billing_country(),
                    'shipping' => WC()->customer->get_shipping_country(),
                ];
            }
        };
        add_action('woocommerce_before_calculate_totals', $capture_country, PHP_INT_MAX);

        try {
            $gateway = new $gateway_class();
            $result = $gateway->createOrder($billing_address, $address, $items, '');
        } finally {
            remove_action('woocommerce_before_calculate_totals', $capture_country, PHP_INT_MAX);
        }

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->order_ids[] = $result['data']['id'];

        $order = wc_get_order($result['data']['id']);
        $order_items = array_values($order->get_items('line_item'));
        $this->assertCount(1, $order_items);
        $this->assertSame($product_id, $order_items[0]->get_product_id());
        $this->assertSame($variation_id, $order_items[0]->get_variation_id());
        $this->assertSame('42', $order_items[0]->get_meta('shoe-size'));
        $this->assertSame([['billing' => 'DE', 'shipping' => 'BE']], $calculated_countries);
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    public function walletGateways(): array
    {
        return [
            'Apple Pay' => [ApplepayGateway::class],
            'Google Pay' => [GooglepayGateway::class],
        ];
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_order_preserves_matching_third_party_cart_item_data(string $gateway_class): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $variation = ['attribute_shoe-size' => '42'];
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            1,
            $variation_id,
            $variation,
            ['third_party_data' => ['source' => 'live cart']]
        );
        $this->assertIsString($cart_item_key);
        $state_before = $this->captureLiveCheckoutState();

        $copy_third_party_data = static function ($item, $key, $cart_item) {
            if (isset($cart_item['third_party_data']['source'])) {
                $item->add_meta_data('third_party_source', $cart_item['third_party_data']['source'], true);
            }
        };
        add_action('woocommerce_checkout_create_order_line_item', $copy_third_party_data, 10, 3);

        $items = [
            [
                'type' => 'product',
                'id' => $variation_id,
                'name' => 'Wildcard shoes',
                'price' => 25,
                'quantity' => 1,
                'attributes' => $variation,
            ],
        ];
        $address = [
            'givenName' => 'Jane',
            'familyName' => 'Doe',
            'emailAddress' => 'jane@example.com',
            'phoneNumber' => '+31612345678',
            'addressLines' => ['Kerkstraat 42'],
            'locality' => 'Amsterdam',
            'administrativeArea' => 'NH',
            'postalCode' => '1017GB',
            'countryCode' => 'NL',
        ];

        try {
            $gateway = new $gateway_class();
            $result = $gateway->createOrder($address, $address, $items, '');
        } finally {
            remove_action('woocommerce_checkout_create_order_line_item', $copy_third_party_data, 10);
        }

        $this->assertIsArray($result);
        $this->order_ids[] = $result['data']['id'];
        $order = wc_get_order($result['data']['id']);
        $order_items = array_values($order->get_items('line_item'));

        $this->assertCount(1, $order_items);
        $this->assertSame('live cart', $order_items[0]->get_meta('third_party_source'));
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_matching_wallet_order_detaches_product_objects_from_the_live_cart(
        string $gateway_class
    ): void
    {
        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $variation = ['attribute_shoe-size' => '42'];
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id, $variation);
        $this->assertIsString($cart_item_key);
        $live_product = WC()->cart->get_cart_contents()[$cart_item_key]['data'];
        $this->assertSame('25.00', $live_product->get_price());

        $mutate_temporary_product = static function ($cart) use ($cart_item_key) {
            $contents = $cart->get_cart_contents();
            if (isset($contents[$cart_item_key])) {
                $contents[$cart_item_key]['data']->set_price('1.00');
            }
        };
        add_action('woocommerce_before_calculate_totals', $mutate_temporary_product, PHP_INT_MAX);

        $items = [
            [
                'type' => 'product',
                'id' => $variation_id,
                'name' => 'Wildcard shoes',
                'price' => 25,
                'quantity' => 1,
                'attributes' => $variation,
            ],
        ];
        $address = [
            'givenName' => 'Jane',
            'familyName' => 'Doe',
            'emailAddress' => 'jane@example.com',
            'phoneNumber' => '+31612345678',
            'addressLines' => ['Kerkstraat 42'],
            'locality' => 'Amsterdam',
            'administrativeArea' => 'NH',
            'postalCode' => '1017GB',
            'countryCode' => 'NL',
        ];

        try {
            $gateway = new $gateway_class();
            $result = $gateway->createOrder($address, $address, $items, '');
        } finally {
            remove_action('woocommerce_before_calculate_totals', $mutate_temporary_product, PHP_INT_MAX);
        }

        $this->assertIsArray($result);
        $this->order_ids[] = $result['data']['id'];
        $this->assertSame('25.00', $live_product->get_price());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_order_does_not_duplicate_flattened_bundle_children(string $gateway_class): void
    {
        $live_product_id = $this->createSimpleProduct('Keep in cart', '15.00');
        $live_cart_key = WC()->cart->add_to_cart($live_product_id, 1);
        $this->assertIsString($live_cart_key);
        $state_before = $this->captureLiveCheckoutState();

        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $child_product_id = $this->createSimpleProduct('Required bundle child', '5.00');
        $add_required_child = static function ($cart_item_key, $added_product_id) use (
            $product_id,
            $child_product_id
        ) {
            if ((int) $added_product_id === $product_id) {
                WC()->cart->add_to_cart($child_product_id, 1);
            }
        };
        add_action('woocommerce_add_to_cart', $add_required_child, 10, 2);

        $items = [
            [
                'type' => 'product',
                'id' => $variation_id,
                'name' => 'Wildcard shoes',
                'price' => 25,
                'quantity' => 1,
                'attributes' => ['attribute_shoe-size' => '42'],
            ],
            [
                'type' => 'product',
                'id' => $child_product_id,
                'name' => 'Required bundle child',
                'price' => 5,
                'quantity' => 1,
                'attributes' => [],
            ],
        ];
        $address = [
            'givenName' => 'Jane',
            'familyName' => 'Doe',
            'emailAddress' => 'jane@example.com',
            'phoneNumber' => '+31612345678',
            'addressLines' => ['Kerkstraat 42'],
            'locality' => 'Amsterdam',
            'administrativeArea' => 'NH',
            'postalCode' => '1017GB',
            'countryCode' => 'NL',
        ];

        try {
            $gateway = new $gateway_class();
            $result = $gateway->createOrder($address, $address, $items, '');
        } finally {
            remove_action('woocommerce_add_to_cart', $add_required_child, 10);
        }

        $this->assertIsArray($result);
        $this->order_ids[] = $result['data']['id'];
        $order_items = array_values(wc_get_order($result['data']['id'])->get_items('line_item'));
        $quantities = [];
        foreach ($order_items as $order_item) {
            $id = $order_item->get_variation_id() ?: $order_item->get_product_id();
            $quantities[$id] = $order_item->get_quantity();
        }

        $this->assertSame(1, $quantities[$variation_id] ?? null);
        $this->assertSame(1, $quantities[$child_product_id] ?? null);
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_order_rejects_product_lines_added_during_totals(string $gateway_class): void
    {
        $live_product_id = $this->createSimpleProduct('Keep in cart', '15.00');
        $live_cart_key = WC()->cart->add_to_cart($live_product_id, 1);
        $this->assertIsString($live_cart_key);
        $state_before = $this->captureLiveCheckoutState();

        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $injected_product_id = $this->createSimpleProduct('Injected during totals', '9.00');
        $live_cart = WC()->cart;
        $injected = false;
        $inject_product = static function ($cart) use ($live_cart, $injected_product_id, &$injected) {
            if ($cart !== $live_cart && ! $injected) {
                $injected = true;
                $cart->add_to_cart($injected_product_id, 1);
            }
        };
        add_action('woocommerce_before_calculate_totals', $inject_product, PHP_INT_MAX);

        $items = [
            [
                'type' => 'product',
                'id' => $variation_id,
                'name' => 'Wildcard shoes',
                'price' => 25,
                'quantity' => 1,
                'attributes' => ['attribute_shoe-size' => '42'],
            ],
        ];
        $address = [
            'givenName' => 'Jane',
            'familyName' => 'Doe',
            'emailAddress' => 'jane@example.com',
            'phoneNumber' => '+31612345678',
            'addressLines' => ['Kerkstraat 42'],
            'locality' => 'Amsterdam',
            'administrativeArea' => 'NH',
            'postalCode' => '1017GB',
            'countryCode' => 'NL',
        ];
        $order_ids_before = wc_get_orders(['limit' => -1, 'return' => 'ids']);

        try {
            $gateway = new $gateway_class();
            $result = $gateway->createOrder($address, $address, $items, '');
        } finally {
            remove_action('woocommerce_before_calculate_totals', $inject_product, PHP_INT_MAX);
        }

        $this->assertFalse($result);
        $this->assertSame($order_ids_before, wc_get_orders(['limit' => -1, 'return' => 'ids']));
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_order_handles_a_failed_isolated_add_without_touching_the_live_cart(
        string $gateway_class
    ): void
    {
        $live_product_id = $this->createSimpleProduct('Keep in cart', '15.00');
        $live_cart_key = WC()->cart->add_to_cart($live_product_id, 1);
        $this->assertIsString($live_cart_key);
        $state_before = $this->captureLiveCheckoutState();

        [$product_id, $variation_id] = $this->createWildcardVariationProduct();
        $items = [
            [
                'type' => 'product',
                'id' => $variation_id,
                'name' => 'Wildcard shoes',
                'price' => 25,
                'quantity' => 1,
                'attributes' => [],
            ],
        ];
        $address = [
            'givenName' => 'Jane',
            'familyName' => 'Doe',
            'emailAddress' => 'jane@example.com',
            'phoneNumber' => '+31612345678',
            'addressLines' => ['Kerkstraat 42'],
            'locality' => 'Amsterdam',
            'administrativeArea' => 'NH',
            'postalCode' => '1017GB',
            'countryCode' => 'NL',
        ];

        $gateway = new $gateway_class();
        $order_ids_before = wc_get_orders(['limit' => -1, 'return' => 'ids']);
        $result = $gateway->createOrder($address, $address, $items, '');

        $this->assertFalse($result);
        $this->assertSame($order_ids_before, wc_get_orders(['limit' => -1, 'return' => 'ids']));
        $this->assertSame($state_before, $this->captureLiveCheckoutState());
    }

    private function createWildcardVariationProduct(): array
    {
        $attribute = new WC_Product_Attribute();
        $attribute->set_name('shoe-size');
        $attribute->set_options(['41', '42']);
        $attribute->set_visible(true);
        $attribute->set_variation(true);

        $product = new WC_Product_Variable();
        $product->set_name('Wildcard shoes');
        $product->set_status('publish');
        $product->set_attributes([$attribute]);
        $product_id = $product->save();

        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product_id);
        $variation->set_status('publish');
        $variation->set_regular_price('25.00');
        $variation->set_price('25.00');
        $variation->set_attributes(['shoe-size' => '']);
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

    private function captureJsonResponse(callable $endpoint): array
    {
        $die_handler = static function () {
            throw new RuntimeException('wp_die');
        };
        $handler_filter = static function () use ($die_handler) {
            return $die_handler;
        };

        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', $handler_filter);
        ob_start();

        try {
            call_user_func($endpoint);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'wp_die') {
                throw $exception;
            }
        } finally {
            remove_filter('wp_die_ajax_handler', $handler_filter);
            remove_filter('wp_doing_ajax', '__return_true');
        }

        $response = json_decode((string) ob_get_clean(), true);

        $this->assertIsArray($response);

        return $response;
    }

    private function captureLiveCheckoutState(): array
    {
        $session_data = new ReflectionProperty(WC_Session::class, '_data');
        $session_dirty = new ReflectionProperty(WC_Session::class, '_dirty');

        return [
            'cart_object' => WC()->cart,
            'cart_contents' => serialize(WC()->cart->get_cart_contents()),
            'removed_cart_contents' => serialize(WC()->cart->get_removed_cart_contents()),
            'applied_coupons' => WC()->cart->get_applied_coupons(),
            'coupon_discount_totals' => WC()->cart->get_coupon_discount_totals(),
            'coupon_discount_tax_totals' => WC()->cart->get_coupon_discount_tax_totals(),
            'fees' => serialize(WC()->cart->get_fees()),
            'totals' => WC()->cart->get_totals(),
            'notices' => wc_get_notices(),
            'customer_object' => WC()->customer,
            'customer' => WC()->customer->get_data(),
            'session_object' => WC()->session,
            'session_data' => $session_data->getValue(WC()->session),
            'session_dirty' => $session_dirty->getValue(WC()->session),
            'stored_session_data' => method_exists(WC()->session, 'get_session_data')
                ? WC()->session->get_session_data()
                : null,
            'shipping_packages' => WC()->shipping()->packages,
        ];
    }
}
