<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\RefundAction;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

class Test_RefundAction extends TestCase
{
    use HposStorage;

    protected function tearDown(): void
    {
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

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
        $this->enableHpos();

        $order = $this->createOrder();
        $refundAction = $this->createRefundAction($order);
        $transactionKey = 'PENDING_TX_KEY_793';
        $response = $this->createMockResponse(793, $transactionKey);

        $refundAction->finalize($response);

        $this->assertEquals(
            'ok',
            OrderMeta::get($order->get_id(), '_refundbuckaroo' . $transactionKey),
            'Pending refund should set _refundbuckaroo meta to prevent duplicate on push'
        );
    }

    /**
     * _refundbuckaroo<key> is the idempotency lock that stops a replayed Buckaroo
     * push from refunding twice. It is written with $unique = true, so a replay has
     * to leave exactly one value behind.
     */
    public function test_finalize_replayed_twice_writes_the_refund_lock_only_once()
    {
        $this->enableHpos();

        $order = $this->createOrder();
        $refundAction = $this->createRefundAction($order);
        $transactionKey = 'REPLAYED_TX_KEY';
        $response = $this->createMockResponse(190, $transactionKey);

        $refundAction->finalize($response);
        $refundAction->finalize($response);

        $this->assertSame(
            ['ok'],
            OrderMeta::get($order->get_id(), '_refundbuckaroo' . $transactionKey, false),
            'A replayed refund must not add a second lock value'
        );
    }

    public function test_finalize_locks_each_transaction_key_separately()
    {
        $this->enableHpos();

        $order = $this->createOrder();
        $refundAction = $this->createRefundAction($order);

        $refundAction->finalize($this->createMockResponse(190, 'TX_ONE'));
        $refundAction->finalize($this->createMockResponse(190, 'TX_TWO'));

        $this->assertSame(['ok'], OrderMeta::get($order->get_id(), '_refundbuckarooTX_ONE', false));
        $this->assertSame(['ok'], OrderMeta::get($order->get_id(), '_refundbuckarooTX_TWO', false));
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

    /**
     * @param  WC_Order|null  $order  A real order when the test asserts on order meta,
     *                                otherwise a mock is enough
     */
    private function createRefundAction($order = null): RefundAction
    {
        $reflection = new \ReflectionClass(RefundAction::class);
        $refundAction = $reflection->newInstanceWithoutConstructor();

        if ($order === null) {
            $order = $this->createMock(\WC_Order::class);
            $order->method('get_id')->willReturn(99999);
            $order->method('get_transaction_id')->willReturn('ORIG_TX_KEY');
        }

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
}
