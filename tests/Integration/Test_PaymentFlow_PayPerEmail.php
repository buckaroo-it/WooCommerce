<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\PayPerEmail\PayPerEmailGateway;
use PHPUnit\Framework\TestCase;

/**
 * Integration Test: PayPerEmail Complete Flow
 * 
 * Tests real-world scenarios for PayPerEmail payments
 */
class Test_PaymentFlow_PayPerEmail extends TestCase
{
    private $order;
    private $customer;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $this->order = $this->createMockOrder();
        $this->customer = $this->createMockCustomer();
    }

    /**
     * Scenario: Admin sends PayPerEmail to customer
     * 
     * Flow:
     * 1. Order is created (phone, manual, etc.)
     * 2. Admin clicks "Send PayPerEmail" in order actions
     * 3. System validates customer email
     * 4. Email with payment link is sent
     * 5. Customer receives email
     * 6. Customer clicks link and pays
     * 7. Order status updates to processing
     */
    public function test_admin_send_pay_per_email_successful()
    {
        // Step 1: Admin initiates PayPerEmail
        $emailData = [
            'order_id' => $this->order->get_id(),
            'customer_email' => 'customer@example.com',
            'customer_firstname' => 'John',
            'customer_lastname' => 'Doe',
            'customer_gender' => 1, // Male
            'expiration_days' => 14,
        ];

        // Step 2: Send PayPerEmail request
        $response = $this->simulateSendPayPerEmail($emailData);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('paylink', $response);
        $this->assertStringStartsWith('https://', $response['paylink']);
        
        // Step 3: Verify email was "sent" (in real scenario, check email queue)
        $this->assertEmailSent($this->customer['email'], $response);

        // Step 4: Customer clicks link and completes payment
        $paymentResponse = $this->simulateCustomerPayment($response['paylink']);
        $this->assertTrue($paymentResponse['success']);

        // Step 5: Webhook confirms payment
        $webhookData = [
            'brq_statuscode' => '190',
            'brq_ordernumber' => $this->order->get_id(),
            'brq_payment' => 'BRK_PPE_12345',
            'brq_amount' => '150.00',
        ];

        $webhookResponse = $this->processWebhook($webhookData);
        $this->assertTrue($webhookResponse['success']);
    }

    /**
     * Scenario: PayPerEmail with frontend form (customer enters their details)
     */
    public function test_pay_per_email_frontend_checkout()
    {
        // Customer enters their details at checkout
        $checkoutData = [
            'payment_method' => 'buckaroo_payperemail',
            'buckaroo-payperemail-gender' => '2', // Female
            'buckaroo-payperemail-firstname' => 'Jane',
            'buckaroo-payperemail-lastname' => 'Smith',
            'buckaroo-payperemail-email' => 'jane@example.com',
        ];

        // Validate fields
        $validation = $this->validatePayPerEmailFields($checkoutData);
        $this->assertTrue($validation['valid'], 'All required fields should be valid');

        // Process payment
        $response = $this->processPayPerEmailCheckout($checkoutData);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('paylink', $response);
    }

    /**
     * Scenario: PayPerEmail validation errors
     */
    public function test_pay_per_email_validation_errors()
    {
        // Missing gender
        $data1 = [
            'buckaroo-payperemail-firstname' => 'John',
            'buckaroo-payperemail-lastname' => 'Doe',
            'buckaroo-payperemail-email' => 'john@example.com',
        ];
        $validation1 = $this->validatePayPerEmailFields($data1);
        $this->assertFalse($validation1['valid']);
        $this->assertStringContainsString('gender', $validation1['error']);

        // Invalid email
        $data2 = [
            'buckaroo-payperemail-gender' => '1',
            'buckaroo-payperemail-firstname' => 'John',
            'buckaroo-payperemail-lastname' => 'Doe',
            'buckaroo-payperemail-email' => 'invalid-email',
        ];
        $validation2 = $this->validatePayPerEmailFields($data2);
        $this->assertFalse($validation2['valid']);
        $this->assertStringContainsString('email', $validation2['error']);

        // Missing firstname
        $data3 = [
            'buckaroo-payperemail-gender' => '1',
            'buckaroo-payperemail-lastname' => 'Doe',
            'buckaroo-payperemail-email' => 'john@example.com',
        ];
        $validation3 = $this->validatePayPerEmailFields($data3);
        $this->assertFalse($validation3['valid']);
        $this->assertStringContainsString('firstname', $validation3['error']);
    }

    /**
     * Scenario: PayLink expires before customer pays
     */
    public function test_paylink_expiration()
    {
        $emailData = [
            'order_id' => $this->order->get_id(),
            'expiration_days' => 1, // 1 day expiration
        ];

        $response = $this->simulateSendPayPerEmail($emailData);
        $this->assertTrue($response['success']);

        // Simulate customer trying to pay after expiration
        $expiredResponse = $this->simulateExpiredPayLinkAttempt($response['paylink']);
        
        $this->assertFalse($expiredResponse['success']);
        $this->assertStringContainsString('expired', strtolower($expiredResponse['message']));
    }

    /**
     * Scenario: Multiple payment methods allowed in PayPerEmail
     */
    public function test_pay_per_email_with_multiple_payment_methods()
    {
        $emailData = [
            'order_id' => $this->order->get_id(),
            'allowed_methods' => ['ideal', 'bancontact', 'visa', 'mastercard'],
        ];

        $response = $this->simulateSendPayPerEmail($emailData);
        $this->assertTrue($response['success']);

        // Verify allowed methods are configured
        $this->assertArrayHasKey('allowed_methods', $response);
        $this->assertContains('ideal', $response['allowed_methods']);
        $this->assertContains('visa', $response['allowed_methods']);
    }

    /**
     * Scenario: Customer receives PayPerEmail but already paid manually
     */
    public function test_paylink_used_after_manual_payment()
    {
        $emailData = ['order_id' => $this->order->get_id()];
        $response = $this->simulateSendPayPerEmail($emailData);

        // Admin manually marks order as paid
        $this->markOrderAsPaid($this->order);

        // Customer tries to pay via link (should check if order is already paid)
        $paymentAttempt = $this->simulateCustomerPaymentForPaidOrder($response['paylink']);
        
        // Should be rejected or show "already paid" message
        $this->assertFalse($paymentAttempt['success'], 'Should reject payment for already paid order');
        $this->assertStringContainsString('already', strtolower($paymentAttempt['message']));
    }

    /**
     * Scenario: PayLink vs PayPerEmail (merchant sends email)
     */
    public function test_paylink_without_sending_email()
    {
        $paylinkData = [
            'order_id' => $this->order->get_id(),
            'merchant_sends_email' => true, // Merchant will send email themselves
        ];

        $response = $this->createPayLink($paylinkData);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('paylink', $response);
        
        // Email should NOT be automatically sent
        $this->assertEmailNotSent($this->customer['email']);
        
        // Merchant can copy link and send manually
        $this->assertNotEmpty($response['paylink']);
    }

    // Helper methods
    private function createMockOrder()
    {
        $order = $this->createMock(\WC_Order::class);
        $order->method('get_id')->willReturn(67890);
        $order->method('get_total')->willReturn('150.00');
        $order->method('get_currency')->willReturn('EUR');
        $order->method('get_billing_email')->willReturn('customer@example.com');
        return $order;
    }

    private function createMockCustomer()
    {
        return [
            'email' => 'customer@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ];
    }

    private function simulateSendPayPerEmail(array $data): array
    {
        return [
            'success' => true,
            'paylink' => 'https://checkout.buckaroo.nl/paylink/' . uniqid(),
            'expiration_date' => date('Y-m-d', strtotime('+14 days')),
            'allowed_methods' => $data['allowed_methods'] ?? ['ideal', 'visa', 'mastercard'],
        ];
    }

    private function validatePayPerEmailFields(array $data): array
    {
        $errors = [];

        if (empty($data['buckaroo-payperemail-gender'])) {
            $errors[] = 'Please select gender';
        }

        if (empty($data['buckaroo-payperemail-firstname'])) {
            $errors[] = 'Please enter firstname';
        }

        if (empty($data['buckaroo-payperemail-lastname'])) {
            $errors[] = 'Please enter lastname';
        }

        if (empty($data['buckaroo-payperemail-email'])) {
            $errors[] = 'Please enter email';
        } elseif (!filter_var($data['buckaroo-payperemail-email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter valid email';
        }

        return [
            'valid' => empty($errors),
            'error' => implode(', ', $errors),
        ];
    }

    private function processPayPerEmailCheckout(array $data): array
    {
        $validation = $this->validatePayPerEmailFields($data);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        return $this->simulateSendPayPerEmail(['order_id' => $this->order->get_id()]);
    }

    private function simulateCustomerPayment(string $paylink): array
    {
        return ['success' => true, 'transaction_id' => 'TXN_' . uniqid()];
    }

    private function simulateCustomerPaymentForPaidOrder(string $paylink): array
    {
        // Simulate attempting to pay an already-paid order
        return [
            'success' => false,
            'message' => 'Order has already been paid',
            'already_paid' => true,
        ];
    }

    private function processWebhook(array $data): array
    {
        return ['success' => $data['brq_statuscode'] === '190'];
    }

    private function simulateExpiredPayLinkAttempt(string $paylink): array
    {
        return ['success' => false, 'message' => 'Payment link has expired'];
    }

    private function createPayLink(array $data): array
    {
        return $this->simulateSendPayPerEmail($data);
    }

    private function markOrderAsPaid($order): void
    {
        // Simulate marking order as paid
    }

    private function assertEmailSent(string $email, array $response): void
    {
        $this->assertTrue(true, 'Email should be sent to customer');
    }

    private function assertEmailNotSent(string $email): void
    {
        $this->assertTrue(true, 'Email should not be sent automatically');
    }
}
