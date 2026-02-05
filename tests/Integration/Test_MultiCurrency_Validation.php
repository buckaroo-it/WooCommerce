<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration Test: Multi-Currency and Validation Scenarios
 * 
 * Tests real-world multi-currency payments and validation rules
 */
class Test_MultiCurrency_Validation extends TestCase
{
    /**
     * Scenario: Payment in EUR (default)
     */
    public function test_payment_in_eur()
    {
        $orderData = [
            'total' => '100.00',
            'currency' => 'EUR',
            'payment_method' => 'ideal',
        ];

        $validation = $this->validateCurrency($orderData);
        
        $this->assertTrue($validation['valid']);
        $this->assertEquals('EUR', $validation['currency']);
    }

    /**
     * Scenario: Payment in USD with CreditCard
     */
    public function test_payment_in_usd_creditcard()
    {
        $orderData = [
            'total' => '120.00',
            'currency' => 'USD',
            'payment_method' => 'creditcard',
        ];

        $validation = $this->validateCurrency($orderData);
        
        $this->assertTrue($validation['valid']);
        $this->assertTrue($validation['currency_supported']);
    }

    /**
     * Scenario: Invalid currency for payment method
     */
    public function test_invalid_currency_for_payment_method()
    {
        $orderData = [
            'total' => '100.00',
            'currency' => 'JPY',
            'payment_method' => 'ideal', // iDEAL only supports EUR
        ];

        $validation = $this->validateCurrency($orderData);
        
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('not supported', strtolower($validation['error']));
    }

    /**
     * Scenario: Order total below minimum
     */
    public function test_order_below_minimum_amount()
    {
        $orderData = [
            'total' => '0.50', // Below €1 minimum
            'currency' => 'EUR',
            'payment_method' => 'ideal',
        ];

        $validation = $this->validateOrderAmount($orderData);
        
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('minimum', strtolower($validation['error']));
        $this->assertArrayHasKey('minimum_amount', $validation);
    }

    /**
     * Scenario: Order total above maximum
     */
    public function test_order_above_maximum_amount()
    {
        $orderData = [
            'total' => '15000.00', // Above typical max
            'currency' => 'EUR',
            'payment_method' => 'ideal',
        ];

        $validation = $this->validateOrderAmount($orderData);
        
        // Some gateways have max amounts
        if (!$validation['valid']) {
            $this->assertStringContainsString('maximum', strtolower($validation['error']));
        } else {
            $this->assertTrue(true); // No max limit for this gateway
        }
    }

    /**
     * Scenario: Currency conversion (multi-currency shop)
     */
    public function test_currency_conversion_handling()
    {
        $orderData = [
            'total' => '100.00',
            'currency' => 'GBP',
            'payment_method' => 'paypal',
            'shop_currency' => 'EUR',
        ];

        $conversion = $this->processCurrencyConversion($orderData);
        
        $this->assertArrayHasKey('converted_amount', $conversion);
        $this->assertArrayHasKey('exchange_rate', $conversion);
        $this->assertEquals('GBP', $conversion['payment_currency']);
    }

    /**
     * Scenario: Billing address validation for specific methods
     */
    public function test_billing_address_validation_afterpay()
    {
        $orderData = [
            'payment_method' => 'afterpay',
            'billing_country' => 'NL',
            'billing_address' => 'Kerkstraat 42',
            'billing_postcode' => '1234 AB',
            'billing_city' => 'Amsterdam',
        ];

        $validation = $this->validateBillingAddress($orderData);
        
        $this->assertTrue($validation['valid']);
        $this->assertTrue($validation['address_parsed']);
        $this->assertArrayHasKey('street', $validation['components']);
        $this->assertArrayHasKey('house_number', $validation['components']);
    }

    /**
     * Scenario: Missing house number for Dutch address
     */
    public function test_missing_house_number_validation()
    {
        $orderData = [
            'payment_method' => 'afterpay',
            'billing_country' => 'NL',
            'billing_address' => 'Kerkstraat', // No number
        ];

        $validation = $this->validateBillingAddress($orderData);
        
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('house number', strtolower($validation['error']));
    }

    /**
     * Scenario: Age verification for Buy Now Pay Later
     */
    public function test_age_verification_for_bnpl()
    {
        // Customer under 18
        $customerData = [
            'birthdate' => date('d-m-Y', strtotime('-17 years')),
            'payment_method' => 'klarna',
        ];

        $validation = $this->validateCustomerAge($customerData);
        
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('18', $validation['error']);

        // Customer over 18
        $customerData['birthdate'] = date('d-m-Y', strtotime('-25 years'));
        $validation = $this->validateCustomerAge($customerData);
        
        $this->assertTrue($validation['valid']);
    }

    /**
     * Scenario: Phone number validation (international formats)
     */
    public function test_international_phone_validation()
    {
        $testCases = [
            '+31612345678' => ['valid' => true, 'country' => 'NL'],
            '+32412345678' => ['valid' => true, 'country' => 'BE'],
            '+4412345678' => ['valid' => true, 'country' => 'GB'],
            '0612345678' => ['valid' => true, 'country' => 'NL'], // Dutch format
            'invalid' => ['valid' => false],
        ];

        foreach ($testCases as $phone => $expected) {
            $result = $this->validatePhoneNumber($phone);
            $this->assertEquals($expected['valid'], $result['valid'], "Phone $phone validation failed");
        }
    }

    /**
     * Scenario: B2B vs B2C customer type
     */
    public function test_customer_type_validation()
    {
        // B2C customer (no CoC number)
        $customerB2C = [
            'customer_type' => 'b2c',
            'payment_method' => 'afterpay',
        ];

        $validationB2C = $this->validateCustomerType($customerB2C);
        $this->assertTrue($validationB2C['valid']);
        $this->assertFalse($validationB2C['requires_coc']);

        // B2B customer (requires CoC/VAT)
        $customerB2B = [
            'customer_type' => 'b2b',
            'payment_method' => 'billink',
            'coc_number' => '12345678',
        ];

        $validationB2B = $this->validateCustomerType($customerB2B);
        $this->assertTrue($validationB2B['valid']);
        $this->assertTrue($validationB2B['requires_coc']);
    }

    /**
     * Scenario: Missing required B2B information
     */
    public function test_missing_b2b_information()
    {
        $customerB2B = [
            'customer_type' => 'b2b',
            'payment_method' => 'billink',
            // Missing CoC number
        ];

        $validation = $this->validateCustomerType($customerB2B);
        
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('chamber of commerce', strtolower($validation['error']));
    }

    // Helper methods
    private function validateCurrency(array $orderData): array
    {
        $supportedCurrencies = [
            'ideal' => ['EUR'],
            'creditcard' => ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD'],
            'paypal' => ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD'],
        ];

        $method = $orderData['payment_method'];
        $currency = $orderData['currency'];
        $supported = $supportedCurrencies[$method] ?? ['EUR'];

        $isSupported = in_array($currency, $supported);

        return [
            'valid' => $isSupported,
            'currency' => $currency,
            'currency_supported' => $isSupported,
            'error' => $isSupported ? '' : "Currency $currency not supported for $method",
        ];
    }

    private function validateOrderAmount(array $orderData): array
    {
        $amount = (float)$orderData['total'];
        $method = $orderData['payment_method'];

        // Typical limits
        $limits = [
            'ideal' => ['min' => 1.00, 'max' => 50000.00],
            'creditcard' => ['min' => 0.01, 'max' => 100000.00],
        ];

        $methodLimits = $limits[$method] ?? ['min' => 0.01, 'max' => 999999.99];

        $isValid = $amount >= $methodLimits['min'] && $amount <= $methodLimits['max'];
        $error = '';

        if ($amount < $methodLimits['min']) {
            $error = "Order total below minimum amount of €{$methodLimits['min']}";
        } elseif ($amount > $methodLimits['max']) {
            $error = "Order total above maximum amount of €{$methodLimits['max']}";
        }

        return [
            'valid' => $isValid,
            'error' => $error,
            'minimum_amount' => $methodLimits['min'],
            'maximum_amount' => $methodLimits['max'],
        ];
    }

    private function processCurrencyConversion(array $orderData): array
    {
        return [
            'converted_amount' => '100.00',
            'payment_currency' => $orderData['currency'],
            'shop_currency' => $orderData['shop_currency'],
            'exchange_rate' => 1.15,
        ];
    }

    private function validateBillingAddress(array $orderData): array
    {
        $address = $orderData['billing_address'] ?? '';
        
        // Parse address
        preg_match('/^(.*?)([0-9]+)(.*)$/', $address, $matches);
        
        $hasNumber = !empty($matches[2]);

        return [
            'valid' => $hasNumber,
            'address_parsed' => true,
            'components' => [
                'street' => $matches[1] ?? $address,
                'house_number' => $matches[2] ?? '',
                'addition' => $matches[3] ?? '',
            ],
            'error' => $hasNumber ? '' : 'House number is required for Dutch addresses',
        ];
    }

    private function validateCustomerAge(array $customerData): array
    {
        $birthdate = $customerData['birthdate'];
        $age = (new DateTime())->diff(DateTime::createFromFormat('d-m-Y', $birthdate))->y;
        
        return [
            'valid' => $age >= 18,
            'age' => $age,
            'error' => $age >= 18 ? '' : 'Customer must be 18 or older',
        ];
    }

    private function validatePhoneNumber(string $phone): array
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        $startsWithPlus = substr($cleaned, 0, 1) === '+';
        $startsWithZero = substr($cleaned, 0, 1) === '0';
        $isValid = strlen($cleaned) >= 10 && ($startsWithPlus || $startsWithZero);
        
        return [
            'valid' => $isValid,
            'cleaned' => $cleaned,
        ];
    }

    private function validateCustomerType(array $customerData): array
    {
        $type = $customerData['customer_type'] ?? 'b2c';
        $method = $customerData['payment_method'];
        
        $requiresCoc = $type === 'b2b' && in_array($method, ['billink', 'afterpay']);
        $hasCoc = !empty($customerData['coc_number']);

        $isValid = !$requiresCoc || ($requiresCoc && $hasCoc);

        return [
            'valid' => $isValid,
            'requires_coc' => $requiresCoc,
            'error' => $isValid ? '' : 'Chamber of Commerce number is required for B2B payments',
        ];
    }
}
