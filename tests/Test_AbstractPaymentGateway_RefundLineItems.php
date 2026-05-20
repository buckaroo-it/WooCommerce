<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use PHPUnit\Framework\TestCase;

class Test_AbstractPaymentGateway_RefundLineItems extends TestCase
{
    public function test_returns_empty_when_amount_is_null()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->expects($this->never())->method('get_refunds');

        $this->assertSame([], $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, null]));
    }

    public function test_returns_empty_when_amount_is_zero()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->expects($this->never())->method('get_refunds');

        $this->assertSame([], $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 0]));
    }

    public function test_returns_empty_when_amount_is_negative()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->expects($this->never())->method('get_refunds');

        $this->assertSame([], $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, -5.0]));
    }

    public function test_returns_empty_when_order_has_no_refunds()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([]);

        $this->assertSame([], $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 29.99]));
    }

    public function test_returns_empty_when_no_refund_matches_amount()
    {
        $stale = $this->makeRefund(1, 50.00, []);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$stale]);

        $this->assertSame([], $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 29.99]));
    }

    public function test_skips_non_refund_objects()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([new stdClass()]);

        $this->assertSame([], $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 29.99]));
    }

    public function test_extracts_partial_line_item_when_refund_amount_matches()
    {
        $refund = $this->makeRefund(10, 29.99, [
            $this->makeRefundItem(['_refunded_item_id' => 42], -1, -29.99),
        ]);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$refund]);

        $result = $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 29.99]);

        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]['item_id']);
        $this->assertSame(1, $result[0]['qty']);
        $this->assertEquals(29.99, $result[0]['total']);
    }

    public function test_normalizes_multiple_items_to_positive_values()
    {
        $refund = $this->makeRefund(11, 25.50, [
            $this->makeRefundItem(['_refunded_item_id' => 10], -2, -20.00),
            $this->makeRefundItem(['_refunded_item_id' => 11], -1, -5.50),
        ]);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$refund]);

        $result = $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 25.50]);

        $this->assertCount(2, $result);
        $this->assertSame(10, $result[0]['item_id']);
        $this->assertSame(2, $result[0]['qty']);
        $this->assertEquals(20.00, $result[0]['total']);
        $this->assertSame(11, $result[1]['item_id']);
        $this->assertSame(1, $result[1]['qty']);
        $this->assertEquals(5.50, $result[1]['total']);
    }

    public function test_skips_items_without_refunded_item_id()
    {
        $refund = $this->makeRefund(12, 10.00, [
            $this->makeRefundItem(['_refunded_item_id' => 0], -1, -10.00),
            $this->makeRefundItem(['_refunded_item_id' => 7], -1, -10.00),
        ]);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$refund]);

        $result = $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 10.00]);

        $this->assertCount(1, $result);
        $this->assertSame(7, $result[0]['item_id']);
    }

    public function test_skips_items_with_zero_quantity()
    {
        $refund = $this->makeRefund(13, 3.50, [
            $this->makeRefundItem(['_refunded_item_id' => 8], 0, 0.0),
            $this->makeRefundItem(['_refunded_item_id' => 9], -1, -3.50),
        ]);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$refund]);

        $result = $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 3.50]);

        $this->assertCount(1, $result);
        $this->assertSame(9, $result[0]['item_id']);
    }

    public function test_uses_matching_refund_when_other_refunds_have_different_amount()
    {
        $stale = $this->makeRefund(100, 50.00, [
            $this->makeRefundItem(['_refunded_item_id' => 1], -1, -50.00),
        ]);
        $current = $this->makeRefund(101, 29.99, [
            $this->makeRefundItem(['_refunded_item_id' => 42], -1, -29.99),
        ]);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$current, $stale]);

        $result = $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 29.99]);

        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]['item_id']);
    }

    public function test_picks_highest_id_when_multiple_refunds_match_amount()
    {
        $older = $this->makeRefund(50, 10.00, [
            $this->makeRefundItem(['_refunded_item_id' => 1], -1, -10.00),
        ]);
        $newer = $this->makeRefund(51, 10.00, [
            $this->makeRefundItem(['_refunded_item_id' => 2], -1, -10.00),
        ]);

        $order = $this->createMock(\WC_Order::class);
        // Intentionally reversed order to verify ID-based selection (HPOS-safe).
        $order->method('get_refunds')->willReturn([$older, $newer]);

        $result = $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 10.00]);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['item_id']);
    }

    public function test_float_tolerance_matches_within_one_cent()
    {
        $refund = $this->makeRefund(60, 29.9999, [
            $this->makeRefundItem(['_refunded_item_id' => 42], -1, -29.99),
        ]);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$refund]);

        $result = $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 29.99]);

        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]['item_id']);
    }

    public function test_returns_empty_when_matching_refund_has_no_line_items()
    {
        // Push-initiated refunds have empty line_items; helper should
        // return [] so the synthesize fallback can run.
        $empty_refund = $this->makeRefund(70, 29.99, []);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$empty_refund]);

        $this->assertSame([], $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, 29.99]));
    }

    public function test_accepts_string_amount_from_wc_refund_payment()
    {
        // WC core passes amount as a string ($refund->get_amount() returns string).
        $refund = $this->makeRefund(80, 29.99, [
            $this->makeRefundItem(['_refunded_item_id' => 42], -1, -29.99),
        ]);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_refunds')->willReturn([$refund]);

        $result = $this->invokeProtected($this->newGateway(), 'getRefundedLineItemsFromOrder', [$order, '29.99']);

        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]['item_id']);
    }

    private function newGateway()
    {
        return $this->getMockBuilder(AbstractPaymentGateway::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    private function makeRefund(int $id, $amount, array $line_items)
    {
        $refund = $this->createMock(\WC_Order_Refund::class);
        $refund->method('get_id')->willReturn($id);
        $refund->method('get_amount')->willReturn((string) $amount);
        $refund->method('get_items')->with('line_item')->willReturn($line_items);

        return $refund;
    }

    private function makeRefundItem(array $meta, int $quantity, float $total)
    {
        $item = $this->getMockBuilder(\WC_Order_Item_Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_meta', 'get_quantity', 'get_total'])
            ->getMock();

        $item->method('get_meta')->willReturnCallback(
            fn($key) => $meta[$key] ?? ''
        );
        $item->method('get_quantity')->willReturn($quantity);
        $item->method('get_total')->willReturn((string) $total);

        return $item;
    }

    private function invokeProtected(object $target, string $method, array $args = [])
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $args);
    }
}
