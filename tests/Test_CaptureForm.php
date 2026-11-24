<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Order\OrderCapture;
use Buckaroo\Woocommerce\Order\OrderMeta;
use PHPUnit\Framework\TestCase;

/**
 * The "Capture & refund order" box on the order screen, shared by every
 * method that captures from the order screen.
 */
class Test_CaptureForm extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_buckaroo_mastersettings_settings', [
            'culture' => 'en-US',
            'merchantkey' => 'test-merchant',
            'secretkey' => 'test-secret',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }

        delete_option('woocommerce_buckaroo_mastersettings_settings');
        $this->deleteCreatedOrders();
        parent::tearDown();
    }

    public function test_form_escapes_items_and_previous_captures(): void
    {
        $order = $this->createOrderWithCapture('CAP"1<x>');
        $lineItem = current($order->get_items('line_item'));
        $shippingItem = current($order->get_items('shipping'));

        ob_start();
        (new OrderCapture(new KlarnaPayGateway()))->output($order);
        $html = ob_get_clean();

        $this->assertStringContainsString('Mug &lt;b&gt;bold&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);

        $this->assertStringContainsString('data-item-id="' . $lineItem->get_id() . '"', $html);
        $this->assertStringContainsString('data-item-id="' . $shippingItem->get_id() . '"', $html);
        $this->assertStringContainsString('data-unit-price="10"', $html);
        $this->assertStringContainsString('woocommerce-Price-amount', $html);

        $this->assertStringContainsString('data-capture-id="CAP&quot;1&lt;x&gt;"', $html);
        $this->assertStringContainsString('id="bk-refund-reasonCAP&quot;1&lt;x&gt;"', $html);
        $this->assertStringNotContainsString('CAP"1<x>', $html);
    }

    /** Three mugs at 10.00 and flat-rate shipping, one mug already captured. */
    private function createOrderWithCapture(string $captureId): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Mug <b>bold</b>');
        $product->set_regular_price('10');
        $product->set_price('10');
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, 3);

        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title('Flat rate');
        $shipping->set_total('5');
        $order->add_item($shipping);

        $order->set_currency('EUR');
        $order->calculate_totals(false);
        $order->save();

        $lineItem = current($order->get_items('line_item'));
        OrderMeta::add($order, '_wc_order_captures', [
            'id' => $captureId,
            'amount' => '10.00',
            'currency' => 'EUR',
            'line_item_qtys' => wp_json_encode([$lineItem->get_id() => 1]),
            'line_item_totals' => wp_json_encode([$lineItem->get_id() => 10.00]),
        ]);
        $order->save();

        return wc_get_order($order->get_id());
    }
}
