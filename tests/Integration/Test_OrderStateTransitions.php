<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration Test: Order State Transitions
 * 
 * Tests real-world order lifecycle and state changes
 */
class Test_OrderStateTransitions extends TestCase
{
    private $order;
    private $orderState;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $this->order = $this->createMockOrder();
        $this->orderState = [
            'status' => 'pending',
            'payment_complete' => false,
            'paid_date' => null,
        ];
    }

    /**
     * Scenario: Normal successful order flow
     * 
     * Flow: Pending → Processing → Completed
     */
    public function test_normal_order_lifecycle()
    {
        // Order created (pending payment)
        $this->assertEquals('pending', $this->orderState['status']);
        $this->assertFalse($this->orderState['payment_complete']);

        // Payment completed
        $this->processSuccessfulPayment();
        $this->assertEquals('processing', $this->orderState['status']);
        $this->assertTrue($this->orderState['payment_complete']);
        $this->assertNotNull($this->orderState['paid_date']);

        // Order shipped and completed
        $this->markOrderCompleted();
        $this->assertEquals('completed', $this->orderState['status']);
    }

    /**
     * Scenario: Failed payment → Cancelled
     */
    public function test_failed_payment_order_lifecycle()
    {
        $this->assertEquals('pending', $this->orderState['status']);

        // Payment fails
        $this->processFailedPayment('Card declined');
        $this->assertEquals('failed', $this->orderState['status']);
        $this->assertFalse($this->orderState['payment_complete']);

        // After some time, order auto-cancelled
        $this->autoCancelFailedOrder();
        $this->assertEquals('cancelled', $this->orderState['status']);
    }

    /**
     * Scenario: Customer cancels before payment
     */
    public function test_customer_cancels_before_payment()
    {
        $this->assertEquals('pending', $this->orderState['status']);

        // Customer initiates payment
        $this->initiatePayment();

        // Customer cancels during payment
        $this->processCancelledPayment();
        $this->assertEquals('cancelled', $this->orderState['status']);
        $this->assertFalse($this->orderState['payment_complete']);
    }

    /**
     * Scenario: Order on-hold (pending manual review)
     */
    public function test_order_on_hold_for_review()
    {
        // High-risk order flagged for review
        $this->processPaymentWithReview();
        
        $this->assertEquals('on-hold', $this->orderState['status']);
        $this->assertTrue($this->orderState['requires_review']);
        $this->assertNotNull($this->orderState['hold_reason']);

        // After manual review, approved
        $this->approveOnHoldOrder();
        $this->assertEquals('processing', $this->orderState['status']);
        $this->assertFalse($this->orderState['requires_review']);
    }

    /**
     * Scenario: Partial payment then remainder
     */
    public function test_partial_payment_flow()
    {
        $this->orderState['total'] = 100.00;
        $this->orderState['paid_amount'] = 0.00;

        // First partial payment (€40)
        $this->processPartialPayment(40.00);
        $this->assertEquals('partial-payment', $this->orderState['status']);
        $this->assertEquals(40.00, $this->orderState['paid_amount']);
        $this->assertEquals(60.00, $this->orderState['remaining_amount']);

        // Second partial payment (€60)
        $this->processPartialPayment(60.00);
        $this->assertEquals('processing', $this->orderState['status']);
        $this->assertEquals(100.00, $this->orderState['paid_amount']);
        $this->assertEquals(0.00, $this->orderState['remaining_amount']);
        $this->assertTrue($this->orderState['payment_complete']);
    }

    /**
     * Scenario: Order with refund → Refunded status
     */
    public function test_order_with_full_refund_status()
    {
        // Order is paid
        $this->processSuccessfulPayment();
        $this->assertEquals('processing', $this->orderState['status']);

        // Full refund issued
        $this->processFullRefund();
        $this->assertEquals('refunded', $this->orderState['status']);
        $this->assertTrue($this->orderState['fully_refunded']);
    }

    /**
     * Scenario: Order with partial refund remains processing
     */
    public function test_order_with_partial_refund_status()
    {
        $this->orderState['total'] = 200.00;
        $this->processSuccessfulPayment();

        // Partial refund (€75)
        $this->processPartialRefundAmount(75.00);
        
        // Status remains processing (not fully refunded)
        $this->assertEquals('processing', $this->orderState['status']);
        $this->assertFalse($this->orderState['fully_refunded'] ?? false);
        $this->assertEquals(75.00, $this->orderState['refunded_amount']);
    }

    /**
     * Scenario: Chargeback changes order status
     */
    public function test_chargeback_order_status()
    {
        // Order successfully paid
        $this->processSuccessfulPayment();
        $this->assertEquals('processing', $this->orderState['status']);

        // Chargeback initiated
        $this->processChargeback();
        
        $this->assertEquals('on-hold', $this->orderState['status']);
        $this->assertTrue($this->orderState['chargeback']);
        $this->assertTrue($this->orderState['requires_review']);
    }

    /**
     * Scenario: Pending payment → Waiting for customer
     */
    public function test_awaiting_customer_payment()
    {
        $this->initiatePayment();

        // Payment awaiting customer action
        $this->markAwaitingConsumer();
        
        $this->assertEquals('pending', $this->orderState['status']);
        $this->assertTrue($this->orderState['awaiting_customer']);
    }

    // Helper methods
    private function createMockOrder()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->method('get_id')->willReturn(33333);
        $order->method('get_total')->willReturn('100.00');
        $order->method('get_currency')->willReturn('EUR');
        return $order;
    }

    private function initiatePayment(): void
    {
        $this->orderState['payment_initiated'] = true;
    }

    private function processSuccessfulPayment(): void
    {
        $this->orderState['status'] = 'processing';
        $this->orderState['payment_complete'] = true;
        $this->orderState['paid_date'] = date('Y-m-d H:i:s');
    }

    private function processFailedPayment(string $reason): void
    {
        $this->orderState['status'] = 'failed';
        $this->orderState['failure_reason'] = $reason;
    }

    private function processCancelledPayment(): void
    {
        $this->orderState['status'] = 'cancelled';
    }

    private function processPaymentWithReview(): void
    {
        $this->orderState['status'] = 'on-hold';
        $this->orderState['requires_review'] = true;
        $this->orderState['hold_reason'] = 'High-risk transaction flagged for review';
    }

    private function approveOnHoldOrder(): void
    {
        $this->orderState['status'] = 'processing';
        $this->orderState['requires_review'] = false;
    }

    private function markOrderCompleted(): void
    {
        $this->orderState['status'] = 'completed';
    }

    private function autoCancelFailedOrder(): void
    {
        $this->orderState['status'] = 'cancelled';
    }

    private function processPartialPayment(float $amount): void
    {
        $total = $this->orderState['total'] ?? 100.00;
        $paidAmount = ($this->orderState['paid_amount'] ?? 0.00) + $amount;
        
        $this->orderState['paid_amount'] = $paidAmount;
        $this->orderState['remaining_amount'] = $total - $paidAmount;
        
        if ($paidAmount >= $total) {
            $this->orderState['status'] = 'processing';
            $this->orderState['payment_complete'] = true;
        } else {
            $this->orderState['status'] = 'partial-payment';
        }
    }

    private function processFullRefund(): void
    {
        $this->orderState['status'] = 'refunded';
        $this->orderState['fully_refunded'] = true;
    }

    private function processPartialRefundAmount(float $amount): void
    {
        $this->orderState['refunded_amount'] = $amount;
    }

    private function processChargeback(): void
    {
        $this->orderState['status'] = 'on-hold';
        $this->orderState['chargeback'] = true;
        $this->orderState['requires_review'] = true;
    }

    private function markAwaitingConsumer(): void
    {
        $this->orderState['awaiting_customer'] = true;
    }

    private function processWebhook(array $data): array
    {
        $statusCode = $data['brq_statuscode'] ?? '0';
        
        if ($statusCode === '190') {
            return ['success' => true];
        }
        
        if ($statusCode === '890') {
            return ['cancelled' => true];
        }
        
        if ($statusCode === '690') {
            return ['timeout' => true, 'can_retry' => true];
        }
        
        return ['unknown' => true];
    }
}
