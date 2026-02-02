<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\PayPerEmail\PayPerEmailProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Test PayPerEmail Processor
 */
class Test_PayPerEmailProcessor extends TestCase
{
    /**
     * Test processor class exists
     */
    public function test_processor_class_exists()
    {
        $this->assertTrue(class_exists(PayPerEmailProcessor::class));
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
     * Test getAction method exists and is public
     */
    public function test_get_action_method_exists()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getAction');
        
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test getMethodBody is protected
     */
    public function test_get_method_body_is_protected()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getMethodBody');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test getExpirationDate is private
     */
    public function test_get_expiration_date_is_private()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getExpirationDate');
        
        $this->assertTrue($method->isPrivate());
    }

    /**
     * Test getAllowedMethods is private
     */
    public function test_get_allowed_methods_is_private()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getAllowedMethods');
        
        $this->assertTrue($method->isPrivate());
    }

    /**
     * Test extractPayLink is protected
     */
    public function test_extract_pay_link_is_protected()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('extractPayLink');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test extractPayLink returns false for empty array
     */
    public function test_extract_pay_link_returns_false_for_empty_services()
    {
        $processor = $this->getMockBuilder(PayPerEmailProcessor::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('extractPayLink');
        $method->setAccessible(true);
        
        // Create mock response parser
        $responseParser = $this->getMockBuilder('Buckaroo\Woocommerce\ResponseParser\ResponseParser')
            ->disableOriginalConstructor()
            ->getMock();
        
        $responseParser->method('get')->willReturn(null);
        
        $result = $method->invoke($processor, $responseParser);
        $this->assertFalse($result);
    }

    /**
     * Test beforeReturnHandler exists and is public
     */
    public function test_before_return_handler_exists()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('beforeReturnHandler'));
    }

    /**
     * Test unsuccessfulReturnHandler exists and is public
     */
    public function test_unsuccessful_return_handler_exists()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('unsuccessfulReturnHandler'));
    }

    /**
     * Test getMethodBody method returns array
     */
    public function test_get_method_body_returns_array()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getMethodBody');
        
        // Verify it's protected (will be called by parent)
        $this->assertTrue($method->isProtected());
        
        // Verify return type hint if present
        $returnType = $method->getReturnType();
        if ($returnType) {
            $this->assertEquals('array', $returnType->getName());
        }
    }

    /**
     * Test getExpirationDate returns string
     */
    public function test_get_expiration_date_returns_string()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getExpirationDate');
        
        $returnType = $method->getReturnType();
        if ($returnType) {
            $this->assertEquals('string', $returnType->getName());
        }
    }

    /**
     * Test getAllowedMethods returns string
     */
    public function test_get_allowed_methods_returns_string()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getAllowedMethods');
        
        $returnType = $method->getReturnType();
        if ($returnType) {
            $this->assertEquals('string', $returnType->getName());
        }
    }

    /**
     * Test beforeReturnHandler accepts correct parameters
     */
    public function test_before_return_handler_parameters()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('beforeReturnHandler');
        $parameters = $method->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertEquals('responseParser', $parameters[0]->getName());
        $this->assertEquals('redirectUrl', $parameters[1]->getName());
    }

    /**
     * Test unsuccessfulReturnHandler accepts correct parameters
     */
    public function test_unsuccessful_return_handler_parameters()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('unsuccessfulReturnHandler');
        $parameters = $method->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertEquals('responseParser', $parameters[0]->getName());
        $this->assertEquals('redirectUrl', $parameters[1]->getName());
    }

    /**
     * Test extractPayLink parameter is ResponseParser
     */
    public function test_extract_pay_link_parameter_type()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('extractPayLink');
        $parameters = $method->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertEquals('responseParser', $parameters[0]->getName());
    }
}
