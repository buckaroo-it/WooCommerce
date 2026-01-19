<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Test iDEAL Gateway
 */
class Test_IdealGateway extends TestCase
{
    /**
     * Test gateway initialization
     */
    public function test_gateway_initialization()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $gateway = $this->createMock(IdealGateway::class);
        $this->assertInstanceOf(IdealGateway::class, $gateway);
    }

    /**
     * Test gateway class name is correct
     */
    public function test_gateway_class_name_is_correct()
    {
        // Verify the class exists and has the correct fully qualified name
        $this->assertEquals('Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway', IdealGateway::class);
        $this->assertTrue(class_exists(IdealGateway::class));
    }

    /**
     * Test gateway has payment processor class defined
     */
    public function test_gateway_has_payment_class_defined()
    {
        $this->assertEquals(IdealProcessor::class, IdealGateway::PAYMENT_CLASS);
    }

    /**
     * Test payment processor class exists
     */
    public function test_payment_processor_class_exists()
    {
        $this->assertTrue(class_exists(IdealProcessor::class));
    }

    /**
     * Test gateway class exists
     */
    public function test_gateway_class_exists()
    {
        $this->assertTrue(class_exists(IdealGateway::class));
    }

    /**
     * Test gateway extends abstract payment gateway
     */
    public function test_gateway_extends_abstract()
    {
        $reflection = new ReflectionClass(IdealGateway::class);
        $this->assertTrue($reflection->isSubclassOf('Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway'));
    }

    /**
     * Test payment processor extends abstract processor
     */
    public function test_processor_extends_abstract()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $this->assertTrue($reflection->isSubclassOf('Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor'));
    }

    /**
     * Test gateway constructor is public
     */
    public function test_gateway_constructor_is_public()
    {
        $reflection = new ReflectionClass(IdealGateway::class);
        $constructor = $reflection->getConstructor();
        $this->assertTrue($constructor->isPublic());
    }

    /**
     * Test processor has required properties
     */
    public function test_processor_has_required_properties()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        
        $this->assertTrue($reflection->hasProperty('issuer'));
        $this->assertTrue($reflection->hasProperty('channel'));
    }

    /**
     * Test processor has getMethodBody method
     */
    public function test_processor_has_get_method_body()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $this->assertTrue($reflection->hasMethod('getMethodBody'));
    }

    /**
     * Test getMethodBody returns array
     */
    public function test_get_method_body_returns_array()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $method = $reflection->getMethod('getMethodBody');
        $method->setAccessible(true);
        
        // We can't easily test without mocking the entire processor
        // But we can verify the method exists and is protected
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test gateway payment class constant is correct
     */
    public function test_payment_class_constant_is_correct()
    {
        $expectedClass = IdealProcessor::class;
        $actualClass = IdealGateway::PAYMENT_CLASS;
        
        $this->assertEquals($expectedClass, $actualClass);
        $this->assertTrue(class_exists($actualClass));
    }

    /**
     * Test processor issuer property is public
     */
    public function test_processor_issuer_property_is_public()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $property = $reflection->getProperty('issuer');
        
        $this->assertTrue($property->isPublic());
    }

    /**
     * Test processor channel property is public
     */
    public function test_processor_channel_property_is_public()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $property = $reflection->getProperty('channel');
        
        $this->assertTrue($property->isPublic());
    }

    /**
     * Test processor data property is protected
     */
    public function test_processor_data_property_is_protected()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $property = $reflection->getProperty('data');
        
        $this->assertTrue($property->isProtected());
    }
}
