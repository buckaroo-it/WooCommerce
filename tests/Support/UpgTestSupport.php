<?php

use BuckarooDeps\Buckaroo\Upg\BuckarooUpgClient;
use BuckarooDeps\Buckaroo\Upg\ClientOptions;
use BuckarooDeps\Buckaroo\Upg\Testing\FakeHttpClient;

/** Settings, a fake UPG and a Wero order for the UPG tests. Call restoreOptions() in tearDown(). */
trait UpgTestSupport
{
    /** @var FakeHttpClient */
    protected $http;

    /** @var array<string, mixed> */
    private $savedOptions = [];

    /** Keep the current values of these options, so restoreOptions() can put them back. */
    protected function saveOptions(string ...$options): void
    {
        foreach ($options as $option) {
            $this->savedOptions[$option] = get_option($option, null);
        }
    }

    protected function restoreOptions(): void
    {
        remove_all_filters('buckaroo_upg_client');
        foreach ($this->savedOptions as $option => $value) {
            $value === null ? delete_option($option) : update_option($option, $value);
        }
        wp_cache_flush();
    }

    /** A 10.00 EUR Wero order; needs the HposStorage trait. */
    protected function order(string $transactionId = ''): WC_Order
    {
        $order = $this->createOrder();
        $item = new WC_Order_Item_Product();
        $item->set_name('Shirt');
        $item->set_quantity(1);
        $item->set_subtotal('10');
        $item->set_total('10');
        $order->add_item($item);
        $order->set_currency('EUR');
        $order->set_payment_method('buckaroo_wero');
        $order->set_billing_country('NL');
        $order->set_transaction_id($transactionId);
        $order->calculate_totals(false);
        $order->save();

        return wc_get_order($order->get_id());
    }

    /** Every UPG client the plugin builds (for a mode with a key) answers from these FakeHttpClient stubs. */
    protected function fakeUpg(array $stubs): void
    {
        $this->http = new FakeHttpClient($stubs);
        add_filter('buckaroo_upg_client', function ($client) {
            return $client === null ? null : new BuckarooUpgClient('apk_test', new ClientOptions(httpClient: $this->http));
        });
    }
}
