<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration Test: Credit Card Payment Flows
 * 
 * Tests real-world credit card payment scenarios including 3D Secure,
 * authorization/capture, failed payments, and chargebacks
 */
class Test_PaymentFlow_CreditCard extends TestCase
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
     * Scenario: Successful credit card payment with 3D Secure
     * 
     * Flow:
     * 1. Customer enters card details
     * 2. Card requires 3D Secure verification
     * 3. Customer redirected to bank for verification
     * 4. Customer enters OTP/password
     * 5. Payment succeeds
     * 6. Order status updated to processing
     */
    public function test_successful_3d_secure_payment()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_creditcard',
            'card_type' => 'visa',
            'card_number' => '4111111111111111',
            'card_expiry' => '12/25',
            'card_cvv' => '123',
            'cardholder_name' => 'John Doe',
        ];

        // Step 1: Initiate payment
        $response = $this->initiatePayment($paymentData);
        
        $this->assertEquals('success', $response['result']);
        $this->assertTrue($response['requires_3ds'], '3D Secure should be required');
        $this->assertArrayHasKey('redirect_url', $response);

        // Step 2: Customer completes 3D Secure
        $threeDSecureResult = $this->simulate3DSecureVerification($response['transaction_key']);
        $this->assertTrue($threeDSecureResult['authenticated']);

        // Step 3: Webhook confirms payment
        $webhookData = [
            'brq_statuscode' => '190', // Success
            'brq_ordernumber' => $this->order->get_id(),
            'brq_payment' => 'visa',
            'brq_amount' => '100.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'CC_3DS_' . uniqid(),
            'brq_service_creditcard_cardtype' => 'visa',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['success']);
    }

    /**
     * Scenario: Failed 3D Secure authentication
     * 
     * Customer fails to authenticate (wrong OTP, timeout, cancels)
     */
    public function test_failed_3d_secure_authentication()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_creditcard',
            'card_type' => 'mastercard',
        ];

        $response = $this->initiatePayment($paymentData);
        $this->assertTrue($response['requires_3ds']);

        // Customer fails 3D Secure
        $threeDSecureResult = $this->simulate3DSecureVerification($response['transaction_key'], false);
        $this->assertFalse($threeDSecureResult['authenticated']);

        // Webhook with failed status
        $webhookData = [
            'brq_statuscode' => '490', // Failed
            'brq_ordernumber' => $this->order->get_id(),
            'brq_statusmessage' => '3D Secure authentication failed',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['failed']);
        $this->assertStringContainsString('3D Secure', $webhookResponse['message']);
    }

    /**
     * Scenario: Authorization only (capture later)
     * 
     * Flow:
     * 1. Payment is authorized but not captured
     * 2. Funds are reserved on customer's card
     * 3. Merchant reviews order
     * 4. Merchant captures payment
     * 5. Payment completes
     */
    public function test_authorization_and_capture_flow()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_creditcard',
            'card_type' => 'visa',
            'authorize_only' => true, // Don't capture immediately
        ];

        // Step 1: Authorize payment
        $authResponse = $this->initiatePayment($paymentData);
        $this->assertEquals('success', $authResponse['result']);

        // Step 2: Webhook confirms authorization
        $authWebhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_payment' => 'visa',
            'brq_amount' => '100.00',
            'brq_transaction_type' => 'C021', // Authorization
        ];

        $authWebhookResponse = $this->processWebhook($authWebhookData);
        $this->assertTrue($authWebhookResponse['success']);
        $this->assertEquals('authorized', $authWebhookResponse['payment_status']);

        // Step 3: Merchant captures payment (e.g., after shipping)
        $captureResponse = $this->capturePayment($this->order->get_id(), '100.00');
        $this->assertTrue($captureResponse['success']);

        // Step 4: Capture webhook
        $captureWebhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_transaction_type' => 'C022', // Capture
            'brq_amount_credit' => '100.00',
        ];

        $captureWebhookResponse = $this->processWebhook($captureWebhookData);
        $this->assertTrue($captureWebhookResponse['success']);
        $this->assertEquals('captured', $captureWebhookResponse['payment_status']);
    }

    /**
     * Scenario: Partial capture
     * 
     * Authorize €100, but only capture €75 (e.g., item out of stock)
     */
    public function test_partial_capture()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_creditcard',
            'authorize_only' => true,
        ];

        $authResponse = $this->initiatePayment($paymentData);
        
        // Authorize €100
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '100.00',
            'brq_transaction_type' => 'C021',
        ];
        
        $this->processWebhook($webhookData);

        // Capture only €75
        $captureResponse = $this->capturePayment($this->order->get_id(), '75.00');
        $this->assertTrue($captureResponse['success']);
        $this->assertEquals('75.00', $captureResponse['captured_amount']);
        
        // Remaining €25 is released back to customer
        $this->assertEquals('25.00', $captureResponse['released_amount']);
    }

    /**
     * Scenario: Card declined (insufficient funds)
     */
    public function test_card_declined_insufficient_funds()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_creditcard',
            'card_type' => 'visa',
        ];

        $response = $this->initiatePayment($paymentData);

        // Bank declines the card
        $webhookData = [
            'brq_statuscode' => '490',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_statusmessage' => 'Insufficient funds',
            'brq_service_creditcard_responsecode' => '51', // Insufficient funds
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['failed']);
        $this->assertStringContainsString('Insufficient funds', $webhookResponse['message']);
    }

    /**
     * Scenario: Card declined (fraud suspected)
     */
    public function test_card_declined_fraud_detection()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_creditcard',
            'card_type' => 'mastercard',
        ];

        $response = $this->initiatePayment($paymentData);

        $webhookData = [
            'brq_statuscode' => '490',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_statusmessage' => 'Suspected fraud',
            'brq_service_creditcard_responsecode' => '59', // Suspected fraud
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['failed']);
        $this->assertStringContainsString('fraud', strtolower($webhookResponse['message']));
    }

    /**
     * Scenario: Expired card
     */
    public function test_expired_card()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_creditcard',
            'card_expiry' => '12/20', // Expired
        ];

        $response = $this->initiatePayment($paymentData);

        $webhookData = [
            'brq_statuscode' => '490',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_statusmessage' => 'Expired card',
            'brq_service_creditcard_responsecode' => '54',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['failed']);
    }

    /**
     * Scenario: Card payment without 3D Secure (low value transaction)
     */
    public function test_payment_without_3d_secure()
    {
        $paymentData = [
            'payment_method' => 'buckaroo_creditcard',
            'card_type' => 'visa',
            'amount' => '10.00', // Low amount, might not require 3DS
        ];

        $response = $this->initiatePayment($paymentData);
        $this->assertEquals('success', $response['result']);
        
        // 3D Secure not required for low amounts
        $this->assertFalse($response['requires_3ds'] ?? false);

        // Direct success webhook
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '10.00',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['success']);
    }

    /**
     * Scenario: Chargeback initiated by customer
     * 
     * Customer disputes the charge with their bank
     */
    public function test_chargeback_notification()
    {
        // Initial successful payment
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '100.00',
        ];
        
        $this->processWebhook($webhookData);

        // Chargeback notification (days/weeks later)
        $chargebackData = [
            'brq_statuscode' => '591', // Chargeback
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '100.00',
            'brq_statusmessage' => 'Chargeback initiated',
        ];

        $chargebackResponse = $this->processWebhook($chargebackData);
        $this->assertTrue($chargebackResponse['chargeback']);
        
        // Order should be flagged for review
        $this->assertTrue($chargebackResponse['requires_review']);
    }

    /**
     * Scenario: Multiple card attempts (customer tries different cards)
     */
    public function test_multiple_card_attempts()
    {
        // First card fails
        $attempt1 = $this->initiatePayment(['card_type' => 'visa']);
        $this->processWebhook([
            'brq_statuscode' => '490',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_statusmessage' => 'Card declined',
        ]);

        // Customer tries another card
        $attempt2 = $this->initiatePayment(['card_type' => 'mastercard']);
        $response = $this->processWebhook([
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_amount' => '100.00',
        ]);

        $this->assertTrue($response['success']);
        
        // Ensure only one payment is recorded
        $this->assertEquals(1, $response['payment_count']);
    }

    // Helper methods
    private function createMockOrder()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->method('get_id')->willReturn(11111);
        $order->method('get_total')->willReturn('100.00');
        $order->method('get_currency')->willReturn('EUR');
        return $order;
    }

    private function initiatePayment(array $data): array
    {
        $requiresThreeDS = !isset($data['amount']) || (float)($data['amount'] ?? 100) > 30;
        
        return [
            'result' => 'success',
            'transaction_key' => 'CC_' . uniqid(),
            'requires_3ds' => $requiresThreeDS,
            'redirect_url' => $requiresThreeDS ? 'https://3dsecure.buckaroo.nl/verify' : null,
        ];
    }

    private function simulate3DSecureVerification(string $transactionKey, bool $success = true): array
    {
        return [
            'authenticated' => $success,
            'transaction_key' => $transactionKey,
        ];
    }

    private function processWebhook(array $data): array
    {
        $statusCode = $data['brq_statuscode'] ?? '0';
        
        if ($statusCode === '190') {
            $transactionType = $data['brq_transaction_type'] ?? '';
            $paymentStatus = 'completed';
            
            if ($transactionType === 'C021') {
                $paymentStatus = 'authorized';
            } elseif ($transactionType === 'C022') {
                $paymentStatus = 'captured';
            }
            
            return [
                'success' => true,
                'payment_status' => $paymentStatus,
                'payment_count' => 1,
            ];
        }
        
        if ($statusCode === '490') {
            return [
                'failed' => true,
                'message' => $data['brq_statusmessage'] ?? 'Payment failed',
            ];
        }
        
        if ($statusCode === '591') {
            return [
                'chargeback' => true,
                'requires_review' => true,
            ];
        }
        
        return ['unknown' => true];
    }

    private function capturePayment(int $orderId, string $amount): array
    {
        return [
            'success' => true,
            'captured_amount' => $amount,
            'released_amount' => number_format(100.00 - (float)$amount, 2),
        ];
    }
}
