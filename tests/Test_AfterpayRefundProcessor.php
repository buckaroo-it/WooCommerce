<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewRefundProcessor;
use PHPUnit\Framework\TestCase;

class Test_AfterpayRefundProcessor extends TestCase
{
    public function test_get_refund_articles_dispatches_to_partial_when_line_items_present()
    {
        $line_items = [['item_id' => 42, 'qty' => 1]];

        $processor = $this->getMockBuilder(AfterpayNewRefundProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRefundedLineItems', 'getAllArticlesWithRefundType', 'getPartialRefundArticles', 'buildPartialAmountArticles'])
            ->getMock();

        $processor->method('getRefundedLineItems')->willReturn($line_items);
        $processor->expects($this->once())
            ->method('getPartialRefundArticles')
            ->with($line_items)
            ->willReturn([['identifier' => 42, 'price' => 10.00, 'quantity' => 1, 'refundType' => 'Return']]);
        $processor->expects($this->never())->method('getAllArticlesWithRefundType');
        $processor->expects($this->never())->method('buildPartialAmountArticles');

        $result = $this->invokeProtected($processor, 'getRefundArticles');

        $this->assertCount(1, $result);
        $this->assertEquals(42, $result[0]['identifier']);
    }

    public function test_get_refund_articles_uses_partial_amount_fallback_when_available()
    {
        $synthesized = [['identifier' => 42, 'price' => 1.00, 'quantity' => 1, 'refundType' => 'Return']];

        $processor = $this->getMockBuilder(AfterpayNewRefundProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRefundedLineItems', 'buildPartialAmountArticles', 'getAllArticlesWithRefundType'])
            ->getMock();

        $processor->method('getRefundedLineItems')->willReturn([]);
        $processor->method('buildPartialAmountArticles')->willReturn($synthesized);
        $processor->expects($this->never())->method('getAllArticlesWithRefundType');

        $result = $this->invokeProtected($processor, 'getRefundArticles');

        $this->assertEquals($synthesized, $result);
    }

    public function test_get_refund_articles_falls_back_to_all_articles_when_no_partial_amount()
    {
        $full_articles = [
            ['identifier' => 1, 'price' => 5.00, 'quantity' => 1, 'refundType' => 'Return'],
            ['identifier' => 2, 'price' => 5.00, 'quantity' => 1, 'refundType' => 'Return'],
        ];

        $processor = $this->getMockBuilder(AfterpayNewRefundProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRefundedLineItems', 'buildPartialAmountArticles', 'getAllArticlesWithRefundType'])
            ->getMock();

        $processor->method('getRefundedLineItems')->willReturn([]);
        $processor->method('buildPartialAmountArticles')->willReturn(null);
        $processor->method('getAllArticlesWithRefundType')->willReturn($full_articles);

        $result = $this->invokeProtected($processor, 'getRefundArticles');

        $this->assertEquals($full_articles, $result);
    }

    public function test_resolve_partial_refund_amount_returns_null_when_no_refunds()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([]);

        $processor = $this->newProcessorWithOrder($order);

        $this->assertNull($this->invokeProtected($processor, 'resolvePartialRefundAmount'));
    }

    public function test_resolve_partial_refund_amount_returns_null_when_amount_equals_order_total()
    {
        $refund = $this->createMock(\WC_Order_Refund::class);
        $refund->method('get_amount')->willReturn(10.00);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$refund]);
        $order->method('get_total')->willReturn('10.00');

        $processor = $this->newProcessorWithOrder($order);

        $this->assertNull($this->invokeProtected($processor, 'resolvePartialRefundAmount'));
    }

    public function test_resolve_partial_refund_amount_returns_refund_amount_for_partial()
    {
        // User's scenario: 1 product @ €10, refund €1.
        $refund = $this->createMock(\WC_Order_Refund::class);
        $refund->method('get_amount')->willReturn(1.00);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$refund]);
        $order->method('get_total')->willReturn('10.00');

        $processor = $this->newProcessorWithOrder($order);

        $this->assertEquals(1.00, $this->invokeProtected($processor, 'resolvePartialRefundAmount'));
    }

    public function test_resolve_partial_refund_amount_within_rounding_tolerance_of_full_refund()
    {
        // Float jitter: refund 9.9999 on a 10.00 order → treat as full.
        $refund = $this->createMock(\WC_Order_Refund::class);
        $refund->method('get_amount')->willReturn(9.9999);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$refund]);
        $order->method('get_total')->willReturn('10.00');

        $processor = $this->newProcessorWithOrder($order);

        $this->assertNull($this->invokeProtected($processor, 'resolvePartialRefundAmount'));
    }

    public function test_build_partial_amount_articles_returns_null_when_no_refund_amount()
    {
        $processor = $this->getMockBuilder(AfterpayNewRefundProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolvePartialRefundAmount', 'resolveFallbackProduct'])
            ->getMock();

        $processor->method('resolvePartialRefundAmount')->willReturn(null);
        $processor->expects($this->never())->method('resolveFallbackProduct');

        $this->assertNull($this->invokeProtected($processor, 'buildPartialAmountArticles'));
    }

    public function test_build_partial_amount_articles_returns_null_when_no_fallback_product()
    {
        $processor = $this->getMockBuilder(AfterpayNewRefundProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolvePartialRefundAmount', 'resolveFallbackProduct'])
            ->getMock();

        $processor->method('resolvePartialRefundAmount')->willReturn(1.00);
        $processor->method('resolveFallbackProduct')->willReturn(null);

        $this->assertNull($this->invokeProtected($processor, 'buildPartialAmountArticles'));
    }

    public function test_build_partial_amount_articles_synthesizes_row_priced_at_refund_amount()
    {
        $product = $this->createMock(\Buckaroo\Woocommerce\Order\OrderItem::class);
        $product->method('get_id')->willReturn(42);
        $product->method('get_title')->willReturn('Widget');
        $product->method('get_vat')->willReturn(21.0);

        $processor = $this->getMockBuilder(AfterpayNewRefundProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolvePartialRefundAmount', 'resolveFallbackProduct'])
            ->getMock();

        $processor->method('resolvePartialRefundAmount')->willReturn(1.00);
        $processor->method('resolveFallbackProduct')->willReturn($product);

        $result = $this->invokeProtected($processor, 'buildPartialAmountArticles');

        $this->assertCount(1, $result);
        $this->assertEquals(42, $result[0]['identifier']);
        $this->assertEquals('Widget', $result[0]['description']);
        $this->assertEquals(1.00, $result[0]['price']);
        $this->assertEquals(1, $result[0]['quantity']);
        $this->assertEquals('Return', $result[0]['refundType']);
        // AfterpayNewRefundProcessor adds vatPercentage via getVatData override.
        $this->assertEquals(21.0, $result[0]['vatPercentage']);
    }

    private function newProcessorWithOrder($order)
    {
        $processor = $this->getMockBuilder(AfterpayNewRefundProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $order_details = $this->createMock(\Buckaroo\Woocommerce\Order\OrderDetails::class);
        $order_details->method('get_order')->willReturn($order);

        $reflection = new ReflectionClass(\Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor::class);
        $prop = $reflection->getProperty('order_details');
        $prop->setAccessible(true);
        $prop->setValue($processor, $order_details);

        return $processor;
    }

    private function invokeProtected(object $target, string $method, array $args = [])
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $args);
    }
}
