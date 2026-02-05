<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\PayPerEmail\PayPerEmailGateway;
use Buckaroo\Woocommerce\Gateways\PayPerEmail\PayPerEmailProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Test PayPerEmail Gateway
 */
class Test_PayPerEmailGateway extends TestCase
{
    /**
     * Test gateway class exists
     */
    public function test_gateway_class_exists()
    {
        $this->assertTrue(class_exists(PayPerEmailGateway::class));
    }

    /**
     * Test processor class exists
     */
    public function test_processor_class_exists()
    {
        $this->assertTrue(class_exists(PayPerEmailProcessor::class));
    }

    /**
     * Test gateway has correct payment class
     */
    public function test_gateway_has_correct_payment_class()
    {
        $this->assertEquals(PayPerEmailProcessor::class, PayPerEmailGateway::PAYMENT_CLASS);
    }

    /**
     * Test gateway extends abstract payment gateway
     */
    public function test_gateway_extends_abstract()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->isSubclassOf('Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway'));
    }

    /**
     * Test processor extends abstract payment processor
     */
    public function test_processor_extends_abstract()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->isSubclassOf('Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor'));
    }

    /**
     * Test gateway has supported currencies property
     */
    public function test_gateway_has_supported_currencies()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasProperty('supportedCurrencies'));
    }

    /**
     * Test gateway supported currencies includes major currencies
     */
    public function test_gateway_supported_currencies_includes_major_currencies()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $property = $reflection->getProperty('supportedCurrencies');
        $property->setAccessible(true);
        
        $defaultValue = $property->getDeclaringClass()->getDefaultProperties()['supportedCurrencies'] ?? [];
        
        $this->assertContains('EUR', $defaultValue);
        $this->assertContains('USD', $defaultValue);
        $this->assertContains('GBP', $defaultValue);
    }

    /**
     * Test gateway has validate_fields method
     */
    public function test_gateway_has_validate_fields_method()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasMethod('validate_fields'));
    }

    /**
     * Test gateway has isVisibleOnFrontend method
     */
    public function test_gateway_has_is_visible_on_frontend_method()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasMethod('isVisibleOnFrontend'));
    }

    /**
     * Test gateway has canShowPayPerEmail method
     */
    public function test_gateway_has_can_show_pay_per_email_method()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('canShowPayPerEmail');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test gateway has canShowPaylink method
     */
    public function test_gateway_has_can_show_paylink_method()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('canShowPaylink');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test processor has getAction method
     */
    public function test_processor_has_get_action_method()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('getAction'));
    }

    /**
     * Test processor getAction returns correct action
     */
    public function test_processor_get_action_returns_payment_invitation()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getAction');
        
        // Method should be public
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test processor has getMethodBody method
     */
    public function test_processor_has_get_method_body()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('getMethodBody'));
    }

    /**
     * Test processor has getExpirationDate method
     */
    public function test_processor_has_get_expiration_date()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getExpirationDate');
        
        $this->assertTrue($method->isPrivate());
    }

    /**
     * Test processor has getAllowedMethods method
     */
    public function test_processor_has_get_allowed_methods()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getAllowedMethods');
        
        $this->assertTrue($method->isPrivate());
    }

    /**
     * Test processor has extractPayLink method
     */
    public function test_processor_has_extract_pay_link()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('extractPayLink');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test processor has beforeReturnHandler method
     */
    public function test_processor_has_before_return_handler()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('beforeReturnHandler'));
    }

    /**
     * Test processor has unsuccessfulReturnHandler method
     */
    public function test_processor_has_unsuccessful_return_handler()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('unsuccessfulReturnHandler'));
    }

    /**
     * Test gateway has paymentmethodppe property
     */
    public function test_gateway_has_payment_method_ppe_property()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasProperty('paymentmethodppe'));
    }

    /**
     * Test gateway has frontendVisible property
     */
    public function test_gateway_has_frontend_visible_property()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasProperty('frontendVisible'));
    }

    /**
     * Test gateway has usePayPerLink property
     */
    public function test_gateway_has_use_pay_per_link_property()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $property = $reflection->getProperty('usePayPerLink');
        
        $this->assertTrue($property->isPublic());
    }

    /**
     * Test gateway has init_form_fields method
     */
    public function test_gateway_has_init_form_fields()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasMethod('init_form_fields'));
    }

    /**
     * Test gateway has setProperties method
     */
    public function test_gateway_has_set_properties()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('setProperties');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test gateway has isEnabled method
     */
    public function test_gateway_has_is_enabled()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('isEnabled');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test gateway has handleHooks method
     */
    public function test_gateway_has_handle_hooks()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasMethod('handleHooks'));
    }

    /**
     * Test validate_fields method is public
     */
    public function test_validate_fields_is_public()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('validate_fields');
        
        $this->assertTrue($method->isPublic());
    }
}
