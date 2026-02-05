<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Ideal\IdealProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Test iDEAL Processor
 */
class Test_IdealProcessor extends TestCase
{
    /**
     * Test processor class exists
     */
    public function test_processor_class_exists()
    {
        $this->assertTrue(class_exists(IdealProcessor::class));
    }

    /**
     * Test processor extends abstract payment processor
     */
    public function test_processor_extends_abstract()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $this->assertTrue($reflection->isSubclassOf('Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor'));
    }

    /**
     * Test processor has issuer property
     */
    public function test_processor_has_issuer_property()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $this->assertTrue($reflection->hasProperty('issuer'));
    }

    /**
     * Test processor has channel property
     */
    public function test_processor_has_channel_property()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $this->assertTrue($reflection->hasProperty('channel'));
    }

    /**
     * Test processor has data property
     */
    public function test_processor_has_data_property()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $this->assertTrue($reflection->hasProperty('data'));
    }

    /**
     * Test issuer property is public
     */
    public function test_issuer_property_is_public()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $property = $reflection->getProperty('issuer');
        $this->assertTrue($property->isPublic());
    }

    /**
     * Test channel property is public
     */
    public function test_channel_property_is_public()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $property = $reflection->getProperty('channel');
        $this->assertTrue($property->isPublic());
    }

    /**
     * Test data property is protected
     */
    public function test_data_property_is_protected()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $property = $reflection->getProperty('data');
        $this->assertTrue($property->isProtected());
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
     * Test getMethodBody is protected
     */
    public function test_get_method_body_is_protected()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $method = $reflection->getMethod('getMethodBody');
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test getMethodBody returns array
     */
    public function test_get_method_body_returns_array()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $method = $reflection->getMethod('getMethodBody');
        
        $returnType = $method->getReturnType();
        if ($returnType) {
            $this->assertEquals('array', $returnType->getName());
        }
    }

    /**
     * Test getMethodBody has no parameters
     */
    public function test_get_method_body_has_no_parameters()
    {
        $reflection = new ReflectionClass(IdealProcessor::class);
        $method = $reflection->getMethod('getMethodBody');
        $parameters = $method->getParameters();
        
        $this->assertCount(0, $parameters);
    }
}
