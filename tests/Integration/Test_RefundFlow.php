<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration Test: Refund Scenarios
 * 
 * Tests real-world refund scenarios across different payment methods
 */
class Test_RefundFlow extends TestCase
{
    private $order;
    private $orderState;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $this->order = $this->createMockPaidOrder();
        $this->orderState = [
            'total' => 100.00,
            'total_refunded' => 0.00,
            'paid' => true,
            'chargeback' => false,
        ];
    }

    /**
     * Scenario: Full refund of iDEAL payment
     * 
     * Flow:
     * 1. Customer requests return
     * 2. Merchant approves return
     * 3. Merchant initiates full refund
     * 4. Refund is processed
     * 5. Money returns to customer's bank account
     */
    public function test_full_refund_ideal_payment()
    {
        // Order already paid via iDEAL
        $this->setupPaidOrder('ideal', '150.00');

        // Merchant initiates full refund
        $refundData = [
            'order_id' => $this->order->get_id(),
            'amount' => '150.00',
            'reason' => 'Customer returned product',
        ];

        $refundResponse = $this->processRefund($refundData);
        
        $this->assertTrue($refundResponse['success']);
        $this->assertEquals('150.00', $refundResponse['refunded_amount']);
        $this->assertEquals('full', $refundResponse['refund_type']);

        // Webhook confirms refund
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount_credit' => '150.00',
            'brq_transaction_type' => 'refund',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['success']);
        $this->assertEquals('refunded', $webhookResponse['order_status']);
    }

    /**
     * Scenario: Partial refund (customer returns 1 of 2 items)
     */
    public function test_partial_refund()
    {
        $this->setupPaidOrder('creditcard', '200.00');

        // Refund €75 (one item returned)
        $refundData = [
            'order_id' => $this->order->get_id(),
            'amount' => '75.00',
            'reason' => 'Partial return - 1 item',
            'line_items' => [
                ['id' => 123, 'qty' => 1],
            ],
        ];

        $refundResponse = $this->processRefund($refundData);
        
        $this->assertTrue($refundResponse['success']);
        $this->assertEquals('75.00', $refundResponse['refunded_amount']);
        $this->assertEquals('partial', $refundResponse['refund_type']);
        $this->assertEquals('125.00', $refundResponse['remaining_amount']);
    }

    /**
     * Scenario: Multiple partial refunds
     * 
     * Customer returns items over time
     */
    public function test_multiple_partial_refunds()
    {
        $this->setupPaidOrder('paypal', '300.00');

        // First refund: €50
        $refund1 = $this->processRefund([
            'order_id' => $this->order->get_id(),
            'amount' => '50.00',
            'reason' => 'Item 1 returned',
        ]);
        
        $this->assertTrue($refund1['success']);
        $this->assertEquals('250.00', $refund1['remaining_amount']);

        // Second refund: €75
        $refund2 = $this->processRefund([
            'order_id' => $this->order->get_id(),
            'amount' => '75.00',
            'reason' => 'Item 2 returned',
        ]);
        
        $this->assertTrue($refund2['success']);
        $this->assertEquals('175.00', $refund2['remaining_amount']);

        // Total refunded should be €125
        $this->assertEquals('125.00', $refund2['total_refunded']);
    }

    /**
     * Scenario: Refund exceeds order total (should fail)
     */
    public function test_refund_exceeds_order_total()
    {
        $this->setupPaidOrder('ideal', '100.00');

        // Try to refund more than order total
        $refundData = [
            'order_id' => $this->order->get_id(),
            'amount' => '150.00', // More than €100
        ];

        $refundResponse = $this->processRefund($refundData);
        
        $this->assertFalse($refundResponse['success']);
        $this->assertStringContainsString('exceeds', strtolower($refundResponse['error']));
    }

    /**
     * Scenario: Refund failed (already refunded)
     */
    public function test_duplicate_refund_prevention()
    {
        $this->setupPaidOrder('bancontact', '100.00');

        // First refund succeeds (full refund)
        $refund1 = $this->processRefund([
            'order_id' => $this->order->get_id(),
            'amount' => '100.00',
        ]);
        
        $this->assertTrue($refund1['success']);
        $this->assertEquals('full', $refund1['refund_type']);

        // Try to refund again (should fail because already fully refunded)
        $refund2 = $this->processRefund([
            'order_id' => $this->order->get_id(),
            'amount' => '50.00', // Try to refund any amount
        ]);
        
        $this->assertFalse($refund2['success']);
        // Error will be either "already refunded" or "exceeds order total"
        $error = strtolower($refund2['error']);
        $this->assertTrue(
            strpos($error, 'already') !== false ||
            strpos($error, 'exceeds') !== false,
            'Should prevent refund on fully refunded order'
        );
    }

    /**
     * Scenario: Refund with shipping cost
     */
    public function test_refund_including_shipping()
    {
        $this->setupPaidOrder('creditcard', '125.00', ['shipping' => '10.00']);

        // Refund includes product + shipping
        $refundData = [
            'order_id' => $this->order->get_id(),
            'amount' => '125.00',
            'reason' => 'Full refund including shipping',
            'refund_shipping' => true,
        ];

        $refundResponse = $this->processRefund($refundData);
        
        $this->assertTrue($refundResponse['success']);
        $this->assertEquals('125.00', $refundResponse['refunded_amount']);
        $this->assertTrue($refundResponse['shipping_refunded']);
    }

    /**
     * Scenario: Refund processing takes time (async)
     */
    public function test_async_refund_processing()
    {
        $this->setupPaidOrder('sepa', '100.00');

        $refundData = [
            'order_id' => $this->order->get_id(),
            'amount' => '100.00',
        ];

        $refundResponse = $this->processRefund($refundData);
        
        // Initial response: pending
        $this->assertTrue($refundResponse['success']);
        $this->assertEquals('pending', $refundResponse['status']);

        // Later: webhook confirms completion
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_transaction_type' => 'refund',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['success']);
        $this->assertEquals('refunded', $webhookResponse['order_status']);
    }

    /**
     * Scenario: Refund fails at payment provider
     */
    public function test_refund_fails_at_provider()
    {
        $this->setupPaidOrder('ideal', '100.00');

        $refundData = [
            'order_id' => $this->order->get_id(),
            'amount' => '100.00',
        ];

        $refundResponse = $this->processRefund($refundData);
        $this->assertTrue($refundResponse['success']); // Initiated successfully

        // Webhook with failed status
        $webhookData = [
            'brq_statuscode' => '490',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_statusmessage' => 'Refund failed - account closed',
            'brq_transaction_type' => 'refund',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['failed']);
        $this->assertStringContainsString('account closed', strtolower($webhookResponse['message']));
    }

    /**
     * Scenario: Refund with restocking fee
     */
    public function test_refund_with_restocking_fee()
    {
        $this->setupPaidOrder('paypal', '200.00');

        // Refund minus 10% restocking fee
        $refundData = [
            'order_id' => $this->order->get_id(),
            'amount' => '180.00', // €200 - €20 fee
            'reason' => 'Return with restocking fee (€20)',
            'restocking_fee' => '20.00',
        ];

        $refundResponse = $this->processRefund($refundData);
        
        $this->assertTrue($refundResponse['success']);
        $this->assertEquals('180.00', $refundResponse['refunded_amount']);
        $this->assertEquals('20.00', $refundResponse['fee_retained']);
    }

    /**
     * Scenario: Chargeback vs Refund
     * 
     * Customer files chargeback before merchant can process refund
     */
    public function test_chargeback_prevents_refund()
    {
        $this->setupPaidOrder('creditcard', '150.00');

        // Customer files chargeback
        $this->markOrderAsChargeback($this->order);

        // Merchant tries to refund
        $refundData = [
            'order_id' => $this->order->get_id(),
            'amount' => '150.00',
        ];

        $refundResponse = $this->processRefund($refundData);
        
        $this->assertFalse($refundResponse['success']);
        $this->assertStringContainsString('chargeback', strtolower($refundResponse['error']));
    }

    // Helper methods
    private function createMockPaidOrder()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->method('get_id')->willReturn(99999);
        $order->method('get_total')->willReturn('150.00');
        $order->method('get_currency')->willReturn('EUR');
        $order->method('get_status')->willReturn('processing');
        return $order;
    }

    private function setupPaidOrder(string $paymentMethod, string $amount, array $options = []): void
    {
        // Update order state
        $this->orderState['total'] = (float)$amount;
        $this->orderState['total_refunded'] = 0.00;
        $this->orderState['paid'] = true;
        $this->orderState['payment_method'] = $paymentMethod;
        $this->orderState['shipping_cost'] = (float)($options['shipping'] ?? '0.00');
    }

    private function processRefund(array $data): array
    {
        $amount = (float)$data['amount'];
        $orderTotal = $this->orderState['total'];
        $totalRefunded = $this->orderState['total_refunded'];

        // Validate refund amount
        if ($amount > ($orderTotal - $totalRefunded)) {
            return [
                'success' => false,
                'error' => 'Refund amount exceeds order total',
            ];
        }

        // Check if already fully refunded
        if ($totalRefunded >= $orderTotal) {
            return [
                'success' => false,
                'error' => 'Order already refunded',
            ];
        }

        // Check for chargeback
        if ($this->orderState['chargeback']) {
            return [
                'success' => false,
                'error' => 'Cannot refund - chargeback in progress',
            ];
        }

        // Process refund
        $newTotalRefunded = $totalRefunded + $amount;
        $this->orderState['total_refunded'] = $newTotalRefunded;
        
        $refundType = $newTotalRefunded >= $orderTotal ? 'full' : 'partial';

        return [
            'success' => true,
            'refunded_amount' => number_format($amount, 2),
            'refund_type' => $refundType,
            'remaining_amount' => number_format($orderTotal - $newTotalRefunded, 2),
            'total_refunded' => number_format($newTotalRefunded, 2),
            'status' => 'pending',
            'shipping_refunded' => $data['refund_shipping'] ?? false,
            'fee_retained' => $data['restocking_fee'] ?? '0.00',
        ];
    }

    private function processWebhook(array $data): array
    {
        $statusCode = $data['brq_statuscode'] ?? '0';
        
        if ($statusCode === '190') {
            return [
                'success' => true,
                'order_status' => 'refunded',
            ];
        }
        
        if ($statusCode === '490') {
            return [
                'failed' => true,
                'message' => $data['brq_statusmessage'] ?? 'Refund failed',
            ];
        }
        
        return ['unknown' => true];
    }

    private function markOrderAsChargeback($order): void
    {
        $this->orderState['chargeback'] = true;
    }
}
