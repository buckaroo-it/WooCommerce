<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Hooks\OrderActions;
use Buckaroo\Woocommerce\Order\OrderMeta;
use PHPUnit\Framework\TestCase;

/**
 * Under HPOS the admin hands these hooks an order object rather than a post.
 */
class Test_OrderActions extends TestCase
{
    use HposStorage;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        $this->enableHpos();
    }

    protected function tearDown(): void
    {
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_test_mode_notice_shows_for_an_order_object_on_an_hpos_store()
    {
        $order = $this->createOrder();
        OrderMeta::update($order, '_buckaroo_order_in_test_mode', true);

        $output = $this->renderTestModeNotice(wc_get_order($order->get_id()));

        $this->assertStringContainsString('test mode', $output);
    }

    public function test_test_mode_notice_is_silent_when_the_order_was_not_in_test_mode()
    {
        $order = $this->createOrder();

        $output = $this->renderTestModeNotice(wc_get_order($order->get_id()));

        $this->assertSame('', $output);
    }

    public function test_test_mode_notice_is_silent_when_the_flag_is_switched_off()
    {
        $order = $this->createOrder();
        OrderMeta::update($order, '_buckaroo_order_in_test_mode', false);

        $output = $this->renderTestModeNotice(wc_get_order($order->get_id()));

        $this->assertSame('', $output);
    }

    public function test_test_mode_notice_ignores_a_post_that_is_not_an_order()
    {
        $postId = wp_insert_post([
            'post_title' => 'Not an order',
            'post_type' => 'post',
            'post_status' => 'publish',
        ]);

        $output = $this->renderTestModeNotice(get_post($postId));

        $this->assertSame('', $output);

        wp_delete_post($postId, true);
    }

    /** The action buttons resolve their gateway from _wc_order_selected_payment_method. */
    public function test_order_actions_resolve_their_gateway_from_order_meta()
    {
        $order = $this->createOrder();
        OrderMeta::update($order, '_wc_order_selected_payment_method', 'KlarnaPay');

        $gateway = $this->resolveCapturableGateway($order->get_id());

        $this->assertInstanceOf(AbstractPaymentGateway::class, $gateway);
        $this->assertSame('buckaroo_klarnapay', $gateway->id);
    }

    public function test_order_actions_fall_back_to_the_woocommerce_payment_method()
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_ideal');
        $order->save();

        $gateway = $this->resolveCapturableGateway($order->get_id());

        $this->assertInstanceOf(AbstractPaymentGateway::class, $gateway);
        $this->assertSame('buckaroo_ideal', $gateway->id);
    }

    public function test_order_actions_resolve_nothing_for_a_non_buckaroo_order()
    {
        $order = $this->createOrder();
        $order->set_payment_method('cod');
        $order->save();

        $this->assertNull($this->resolveCapturableGateway($order->get_id()));
    }

    /**
     * @param  WC_Order|WP_Post  $postOrOrder
     */
    private function renderTestModeNotice($postOrOrder): string
    {
        ob_start();
        (new OrderActions())->handleOrderInTestMode($postOrOrder);

        return (string) ob_get_clean();
    }

    private function resolveCapturableGateway(int $orderId): ?AbstractPaymentGateway
    {
        $method = new \ReflectionMethod(OrderActions::class, 'resolveCapturableGateway');
        $method->setAccessible(true);

        return $method->invoke(new OrderActions(), $orderId);
    }
}
