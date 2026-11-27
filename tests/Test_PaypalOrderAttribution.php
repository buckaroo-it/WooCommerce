<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Paypal\PaypalGateway;

/**
 * PayPal fills the order attribution only for orders that have none, such as
 * PayPal Express orders, and keeps what the checkout recorded (BTI-1880).
 */
class Test_PaypalOrderAttribution extends WP_UnitTestCase
{
    /** @var WC_Order|null */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);

        $this->order = wc_create_order();
        $this->order->set_currency('EUR');
        $this->order->set_total('10.00');
        $this->order->save();
    }

    protected function tearDown(): void
    {
        if ($this->order instanceof WC_Order) {
            $this->order->delete(true);
        }
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        wc_clear_notices();

        parent::tearDown();
    }

    public function test_the_attribution_of_the_checkout_is_kept(): void
    {
        $this->order->update_meta_data('_wc_order_attribution_source_type', 'utm');
        $this->order->update_meta_data('_wc_order_attribution_utm_source', 'newsletter');
        $this->order->save();

        $order = $this->pay();

        $this->assertSame(['utm'], $this->metaValues($order, '_wc_order_attribution_source_type'));
        $this->assertSame(['newsletter'], $this->metaValues($order, '_wc_order_attribution_utm_source'));
    }

    public function test_an_order_without_attribution_is_marked_as_direct(): void
    {
        $order = $this->pay();

        $this->assertSame(['typein'], $this->metaValues($order, '_wc_order_attribution_source_type'));
        $this->assertSame(['(direct)'], $this->metaValues($order, '_wc_order_attribution_utm_source'));
    }

    /**
     * Pays the order with an amount limit it exceeds, so the gateway stops
     * before it sends a request to Buckaroo.
     */
    private function pay(): WC_Order
    {
        $gateway = new PaypalGateway();
        $gateway->maxvalue = 1;

        $this->assertSame(['result' => 'failure'], $gateway->process_payment($this->order->get_id()));

        return wc_get_order($this->order->get_id());
    }

    /**
     * @return array<int, mixed>
     */
    private function metaValues(WC_Order $order, string $key): array
    {
        return array_values(array_map(
            static fn ($meta) => $meta->value,
            array_filter($order->get_meta_data(), static fn ($meta) => $meta->key === $key)
        ));
    }
}
