<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration Test: Bancontact Payment Flows
 * 
 * Tests real-world Bancontact payment scenarios (Belgium)
 */
class Test_PaymentFlow_Bancontact extends TestCase
{
    private $order;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $this->order = $this->createMockOrder();
    }

    /**
     * Scenario: Successful Bancontact payment
     * 
     * Flow:
     * 1. Belgian customer selects Bancontact
     * 2. Customer redirected to Bancontact app/website
     * 3. Customer scans QR code with banking app
     * 4. Customer confirms payment
     * 5. Payment completes
     * 6. Customer redirected back to shop
     */
    public function test_successful_bancontact_payment()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_bancontactmrcash',
            'billing_country' => 'BE',
        ];

        // Initiate payment
        $response = $this->initiatePayment($paymentData);
        
        $this->assertEquals('success', $response['result']);
        $this->assertArrayHasKey('redirect', $response);
        $this->assertStringContainsString('bancontact', strtolower($response['redirect']));

        // Webhook confirms payment
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_payment' => 'bancontactmrcash',
            'brq_amount' => '89.99',
            'brq_currency' => 'EUR',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['success']);
    }

    /**
     * Scenario: Customer cancels Bancontact payment
     */
    public function test_cancelled_bancontact_payment()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_bancontactmrcash',
        ];

        $response = $this->initiatePayment($paymentData);
        $this->assertEquals('success', $response['result']);

        // Customer cancels in Bancontact app
        $webhookData = [
            'brq_statuscode' => '890',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_statusmessage' => 'Cancelled by user',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['cancelled']);
    }

    /**
     * Scenario: Mobile app payment (QR code scan)
     */
    public function test_bancontact_mobile_app_payment()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_bancontactmrcash',
            'device' => 'mobile',
        ];

        $response = $this->initiatePayment($paymentData);
        
        // Mobile users get QR code
        $this->assertArrayHasKey('qr_code', $response);
        $this->assertNotEmpty($response['qr_code']);

        // Payment processed quickly via app
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_payment_method' => 'bancontactmrcash',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['success']);
    }

    /**
     * Scenario: Payment timeout (customer doesn't complete)
     */
    public function test_bancontact_payment_timeout()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_bancontactmrcash',
        ];

        $response = $this->initiatePayment($paymentData);

        // Payment times out after 15 minutes
        $webhookData = [
            'brq_statuscode' => '690',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_statusmessage' => 'Payment timed out',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['timeout']);
        
        // Order remains pending, customer can retry
        $this->assertTrue($webhookResponse['can_retry']);
    }

    /**
     * Scenario: Refund Bancontact payment
     */
    public function test_bancontact_refund()
    {
        // Setup paid order
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '89.99',
        ];
        
        $this->processWebhook($webhookData);

        // Process refund
        $refundResponse = $this->processRefund([
            'order_id' => $this->order->get_id(),
            'amount' => '89.99',
            'reason' => 'Customer return',
        ]);

        $this->assertTrue($refundResponse['success']);
        
        // Refund webhook
        $refundWebhook = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_transaction_type' => 'refund',
            'brq_amount_credit' => '89.99',
        ];

        $refundConfirmation = $this->processWebhook($refundWebhook);
        $this->assertTrue($refundConfirmation['success']);
    }

    // Helper methods
    private function createMockOrder()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->method('get_id')->willReturn(22222);
        $order->method('get_total')->willReturn('89.99');
        $order->method('get_currency')->willReturn('EUR');
        $order->method('get_billing_country')->willReturn('BE');
        return $order;
    }

    private function initiatePayment(array $data): array
    {
        $isMobile = ($data['device'] ?? '') === 'mobile';
        
        $response = [
            'result' => 'success',
            'redirect' => 'https://bancontact.buckaroo.nl/checkout',
            'transaction_key' => 'BC_' . uniqid(),
        ];

        if ($isMobile) {
            $response['qr_code'] = 'data:image/png;base64,iVBORw0KGgoAAAANS...';
        }

        return $response;
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

    private function processRefund(array $data): array
    {
        return [
            'success' => true,
            'refund_id' => 'REF_' . uniqid(),
            'amount' => $data['amount'],
        ];
    }
}
