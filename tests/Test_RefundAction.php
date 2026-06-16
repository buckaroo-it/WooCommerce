<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\PaymentProcessors\Actions\RefundAction;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

class Test_RefundAction extends TestCase
{
    /**
     * @dataProvider pendingStatusCodeProvider
     */
    public function test_finalize_returns_true_for_pending_refund_status(int $statusCode)
    {
        $refundAction = $this->createRefundAction();
        $response = $this->createMockResponse($statusCode);

        $result = $refundAction->finalize($response);

        $this->assertTrue($result, "finalize() should return true for pending status $statusCode");
    }

    public function test_finalize_returns_true_for_success_status()
    {
        $refundAction = $this->createRefundAction();
        $response = $this->createMockResponse(190);

        $result = $refundAction->finalize($response);

        $this->assertTrue($result);
    }

    public function test_finalize_returns_wp_error_for_failed_status()
    {
        $refundAction = $this->createRefundAction();
        $response = $this->createMockResponse(490);

        $result = $refundAction->finalize($response);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function test_finalize_sets_refund_meta_for_pending_status()
    {
        $orderId = $this->createTestOrderId();
        $refundAction = $this->createRefundAction($orderId);
        $transactionKey = 'PENDING_TX_KEY_793';
        $response = $this->createMockResponse(793, $transactionKey);

        $refundAction->finalize($response);

        $meta = get_post_meta($orderId, '_refundbuckaroo' . $transactionKey, true);
        $this->assertEquals('ok', $meta, 'Pending refund should set _refundbuckaroo meta to prevent duplicate on push');
    }

    public function test_finalize_adds_pending_order_note()
    {
        $refundAction = $this->createRefundAction();
        $response = $this->createMockResponse(793);

        $order = $this->getOrderFromRefundAction($refundAction);
        $order->expects($this->once())
            ->method('add_order_note')
            ->with($this->stringContains('pending processing'));

        $refundAction->finalize($response);
    }

    public static function pendingStatusCodeProvider(): array
    {
        return [
            'pending processing (791)' => [791],
            'payment on hold (793)' => [793],
        ];
    }

    private function createRefundAction(?int $orderId = null): RefundAction
    {
        $reflection = new \ReflectionClass(RefundAction::class);
        $refundAction = $reflection->newInstanceWithoutConstructor();

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_id')->willReturn($orderId ?? 99999);
        $order->method('get_transaction_id')->willReturn('ORIG_TX_KEY');

        $orderProp = $reflection->getProperty('order');
        $orderProp->setAccessible(true);
        $orderProp->setValue($refundAction, $order);

        return $refundAction;
    }

    private function getOrderFromRefundAction(RefundAction $refundAction)
    {
        $reflection = new \ReflectionClass(RefundAction::class);
        $orderProp = $reflection->getProperty('order');
        $orderProp->setAccessible(true);

        return $orderProp->getValue($refundAction);
    }

    private function createMockResponse(int $statusCode, string $transactionKey = 'TEST_TX_KEY'): TransactionResponse
    {
        $response = $this->getMockBuilder(TransactionResponse::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response->method('isSuccess')->willReturn($statusCode === 190);
        $response->method('isPendingProcessing')->willReturn($statusCode === 791);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getTransactionKey')->willReturn($transactionKey);
        $response->method('get')->willReturn('20.00');
        $response->method('getSomeError')->willReturn('Test error');

        return $response;
    }

    private function createTestOrderId(): int
    {
        return wp_insert_post([
            'post_type' => 'shop_order',
            'post_status' => 'wc-processing',
        ]);
    }
}
