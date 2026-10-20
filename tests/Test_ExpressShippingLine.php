<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;

/**
 * Express wallet orders must charge shipping the way classic checkout does.
 */
class Test_ExpressShippingLine extends WP_UnitTestCase
{
    private const ADDRESS = [
        'givenName' => 'Jane', 'familyName' => 'Doe', 'emailAddress' => 'jane@example.com',
        'addressLines' => ['Kerkstraat 42'], 'locality' => 'Amsterdam',
        'postalCode' => '1017GB', 'countryCode' => 'NL',
    ];

    /** @var int[] */
    private $product_ids = [];

    /** @var int[] */
    private $order_ids = [];

    /** @var string */
    private $rate_id;

    /** @var bool */
    private $shipping_enabled_before;

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

        update_option('woocommerce_ship_to_countries', 'all');
        update_option('woocommerce_calc_taxes', 'no');
        // The wallets only accept EUR orders.
        update_option('woocommerce_currency', 'EUR');

        // A flat rate for every country, from the "locations not covered" zone.
        $instance_id = (new WC_Shipping_Zone(0))->add_shipping_method('flat_rate');
        update_option(
            "woocommerce_flat_rate_{$instance_id}_settings",
            ['enabled' => 'yes', 'title' => 'Flat rate', 'tax_status' => 'none', 'cost' => '5']
        );
        $this->rate_id = "flat_rate:{$instance_id}";
        delete_transient('wc_shipping_method_count');

        $this->shipping_enabled_before = WC()->shipping()->enabled;
        WC()->shipping()->enabled = true;
    }

    protected function tearDown(): void
    {
        WC()->cart->empty_cart(false);
        WC()->shipping()->enabled = $this->shipping_enabled_before;

        foreach ((new WC_Shipping_Zone(0))->get_shipping_methods() as $instance_id => $method) {
            (new WC_Shipping_Zone(0))->delete_shipping_method($instance_id);
        }
        delete_transient('wc_shipping_method_count');

        foreach ($this->order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->delete(true);
            }
        }
        foreach ($this->product_ids as $product_id) {
            wp_delete_post($product_id, true);
        }

        parent::tearDown();
    }

    public function walletGateways(): array
    {
        return [
            'Apple Pay' => [ApplepayGateway::class],
            'Google Pay' => [GooglepayGateway::class],
        ];
    }

    /** @dataProvider walletGateways */
    public function test_physical_order_without_a_shipping_method_is_rejected(string $gateway_class): void
    {
        $this->assertOrderRejected($gateway_class, $this->createProduct(false), '');
    }

    /** @dataProvider walletGateways */
    public function test_physical_order_with_an_unknown_shipping_method_is_rejected(string $gateway_class): void
    {
        $this->assertOrderRejected($gateway_class, $this->createProduct(false), 'flat_rate:999999');
    }

    /** @dataProvider walletGateways */
    public function test_physical_order_with_the_no_shipping_placeholder_is_rejected(string $gateway_class): void
    {
        $this->assertOrderRejected($gateway_class, $this->createProduct(false), 'buckaroo_no_shipping');
    }

    /** @dataProvider walletGateways */
    public function test_physical_order_to_a_country_the_shop_does_not_ship_to_is_rejected(string $gateway_class): void
    {
        update_option('woocommerce_ship_to_countries', 'specific');
        update_option('woocommerce_specific_ship_to_countries', ['BE']);

        $this->assertOrderRejected($gateway_class, $this->createProduct(false), $this->rate_id);
    }

    /** @dataProvider walletGateways */
    public function test_physical_order_with_a_valid_shipping_method_charges_shipping(string $gateway_class): void
    {
        $order = $this->createOrder($gateway_class, $this->createProduct(false), $this->rate_id, '20.00');

        $shipping_lines = array_values($order->get_items('shipping'));
        $this->assertCount(1, $shipping_lines);
        $this->assertSame('flat_rate', $shipping_lines[0]->get_method_id());
        $this->assertSame(5.0, (float) $order->get_shipping_total());
        $this->assertSame(20.0, (float) $order->get_total());
    }

    /** @dataProvider walletGateways */
    public function test_virtual_order_needs_no_shipping_method(string $gateway_class): void
    {
        foreach (['', 'buckaroo_no_shipping'] as $selected_method_id) {
            $order = $this->createOrder($gateway_class, $this->createProduct(true), $selected_method_id, '15.00');

            $this->assertCount(0, $order->get_items('shipping'));
            $this->assertSame(15.0, (float) $order->get_total());
        }
    }

    private function assertOrderRejected(string $gateway_class, int $product_id, string $selected_method_id): void
    {
        $orders_before = wc_get_orders(['limit' => -1, 'return' => 'ids']);

        $result = $this->gateway($gateway_class, '15.00')->createOrder(
            self::ADDRESS,
            self::ADDRESS,
            [['type' => 'product', 'id' => $product_id, 'quantity' => 1]],
            $selected_method_id
        );

        $this->assertFalse($result);
        $this->assertSame($orders_before, wc_get_orders(['limit' => -1, 'return' => 'ids']));
    }

    private function createOrder(string $gateway_class, int $product_id, string $selected_method_id, string $amount): WC_Order
    {
        $result = $this->gateway($gateway_class, $amount)->createOrder(
            self::ADDRESS,
            self::ADDRESS,
            [['type' => 'product', 'id' => $product_id, 'quantity' => 1]],
            $selected_method_id
        );

        $this->assertIsArray($result);
        $this->order_ids[] = $result['data']['id'];

        return wc_get_order($result['data']['id']);
    }

    /**
     * Apple Pay checks the order total against the approved amount while
     * creating the order, so give it the amount the wallet would approve.
     */
    private function gateway(string $gateway_class, string $amount)
    {
        $gateway = new $gateway_class();
        if ($gateway instanceof ApplepayGateway) {
            $property = new ReflectionProperty(ApplepayGateway::class, 'amount');
            $property->setAccessible(true);
            $property->setValue($gateway, $amount);
        }

        return $gateway;
    }

    private function createProduct(bool $virtual): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($virtual ? 'Download' : 'Parcel');
        $product->set_status('publish');
        $product->set_regular_price('15.00');
        $product->set_price('15.00');
        $product->set_virtual($virtual);
        $product_id = $product->save();
        $this->product_ids[] = $product_id;

        return $product_id;
    }
}
