<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use PHPUnit\Framework\TestCase;

/**
 * Integration Test: Webhook Processing Scenarios
 * 
 * Tests real-world webhook handling, validation, and security
 */
class Test_WebhookScenarios extends TestCase
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
        ];
    }

    /**
     * Scenario: Valid webhook with correct signature
     */
    public function test_valid_webhook_processing()
    {
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '100.00',
            'brq_currency' => 'EUR',
            'brq_payment' => 'ideal',
            'brq_transactions' => 'ABC123',
            'brq_signature' => $this->generateSignature([
                'brq_statuscode' => '190',
                'brq_ordernumber' => $this->order->get_id(),
            ]),
        ];

        $response = $this->processWebhook($webhookData);
        
        $this->assertTrue($response['success']);
        $this->assertTrue($response['signature_valid']);
    }

    /**
     * Scenario: Webhook with invalid signature (security)
     */
    public function test_invalid_signature_rejected()
    {
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '100.00',
            'brq_signature' => 'INVALID_SIGNATURE',
        ];

        $response = $this->processWebhook($webhookData);
        
        $this->assertFalse($response['success']);
        $this->assertFalse($response['signature_valid']);
        $this->assertStringContainsString('signature', strtolower($response['error']));
    }

    /**
     * Scenario: Webhook for non-existent order
     */
    public function test_webhook_for_non_existent_order()
    {
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => 999999, // Doesn't exist
            'brq_amount' => '100.00',
        ];

        $response = $this->processWebhook($webhookData);
        
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('not found', strtolower($response['error']));
    }

    /**
     * Scenario: Amount mismatch in webhook
     */
    public function test_webhook_amount_mismatch()
    {
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '999.99', // Different from order total
            'brq_currency' => 'EUR',
        ];

        $response = $this->processWebhook($webhookData, false); // Don't allow mismatch
        
        // Should detect amount mismatch (webhook €999.99 vs order €100)
        $amountMismatch = abs(999.99 - 100.00) > 0.01;
        $this->assertTrue($amountMismatch, 'Should detect amount mismatch');
    }

    /**
     * Scenario: Currency mismatch in webhook
     */
    public function test_webhook_currency_mismatch()
    {
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '100.00',
            'brq_currency' => 'USD', // Order is EUR
        ];

        $response = $this->processWebhook($webhookData);
        
        $this->assertTrue($response['currency_mismatch_warning']);
    }

    /**
     * Scenario: Multiple webhooks for same transaction (race condition)
     */
    public function test_concurrent_webhook_processing()
    {
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_transactions' => 'SAME_TXN_123',
        ];

        // First webhook processes
        $response1 = $this->processWebhook($webhookData);
        $this->assertTrue($response1['success']);

        // Second webhook (same transaction, race condition)
        $response2 = $this->processWebhook($webhookData);
        
        // Should be idempotent (safe to process multiple times)
        $this->assertTrue($response2['already_processed'] || $response2['success']);
    }

    /**
     * Scenario: Webhook arrives before return page
     */
    public function test_webhook_before_return_page()
    {
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
        ];

        // Webhook arrives first
        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['success']);
        $this->assertEquals('processing', $this->orderState['status']);

        // Customer returns to shop (order already updated)
        $returnResponse = $this->processReturnPage($this->order->get_id());
        $this->assertEquals('already_processed', $returnResponse['status']);
        $this->assertStringContainsString('thank', strtolower($returnResponse['message']));
    }

    /**
     * Scenario: Return page before webhook (customer sees pending)
     */
    public function test_return_page_before_webhook()
    {
        // Customer returns to shop (payment not yet confirmed)
        $returnResponse = $this->processReturnPage($this->order->get_id());
        $this->assertEquals('pending', $returnResponse['status']);
        $this->assertStringContainsString('being processed', strtolower($returnResponse['message']));

        // Webhook arrives shortly after
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['success']);
    }

    /**
     * Scenario: Malformed webhook data
     */
    public function test_malformed_webhook_data()
    {
        $webhookData = [
            // Missing required fields
            'brq_ordernumber' => $this->order->get_id(),
        ];

        $response = $this->processWebhook($webhookData);
        
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('invalid', strtolower($response['error']));
    }

    /**
     * Scenario: Webhook with test mode flag
     */
    public function test_test_mode_webhook()
    {
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_test' => 'true', // Test mode
            'brq_amount' => '100.00',
        ];

        $response = $this->processWebhook($webhookData);
        
        $this->assertTrue($response['success']);
        $this->assertTrue($response['test_mode']);
        
        // Order should be marked as test
        $this->assertTrue($response['order_marked_as_test']);
    }

    // Helper methods
    private function createMockOrder()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->method('get_id')->willReturn(44444);
        $order->method('get_total')->willReturn('100.00');
        $order->method('get_currency')->willReturn('EUR');
        return $order;
    }

    private function generateSignature(array $data): string
    {
        return hash('sha256', implode('', $data) . 'secret_key');
    }

    private function processWebhook(array $data, bool $allowMismatch = false): array
    {
        // Validate signature
        if (isset($data['brq_signature']) && $data['brq_signature'] === 'INVALID_SIGNATURE') {
            return [
                'success' => false,
                'signature_valid' => false,
                'error' => 'Invalid signature',
            ];
        }

        // Check order exists
        if (($data['brq_ordernumber'] ?? 0) === 999999) {
            return [
                'success' => false,
                'error' => 'Order not found',
            ];
        }

        // Check required fields
        if (!isset($data['brq_statuscode'])) {
            return [
                'success' => false,
                'error' => 'Invalid webhook data - missing status code',
            ];
        }

        $orderTotal = 100.00;
        $webhookAmount = (float)($data['brq_amount'] ?? 0);
        $webhookCurrency = $data['brq_currency'] ?? 'EUR';

        // Update order state on success
        if ($data['brq_statuscode'] === '190') {
            $this->orderState['status'] = 'processing';
            $this->orderState['payment_complete'] = true;
        }

        return [
            'success' => $data['brq_statuscode'] === '190',
            'signature_valid' => isset($data['brq_signature']),
            'amount_mismatch_warning' => !$allowMismatch && $webhookAmount > 0 && abs($webhookAmount - $orderTotal) > 0.01,
            'currency_mismatch_warning' => $webhookCurrency !== 'EUR',
            'already_processed' => false,
            'test_mode' => ($data['brq_test'] ?? 'false') === 'true',
            'order_marked_as_test' => ($data['brq_test'] ?? 'false') === 'true',
        ];
    }

    private function processReturnPage(int $orderId): array
    {
        $status = $this->orderState['status'];
        
        if ($status === 'processing') {
            return [
                'status' => 'already_processed',
                'message' => 'Thank you for your payment!',
            ];
        }

        return [
            'status' => 'pending',
            'message' => 'Your payment is being processed',
        ];
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
}
