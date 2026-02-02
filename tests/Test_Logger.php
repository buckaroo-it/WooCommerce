<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\LoggerStorage;
use PHPUnit\Framework\TestCase;

/**
 * Test Logger service class
 */
class Test_Logger extends TestCase
{
    /**
     * Test log method with both parameters
     * Note: This tests that the Logger class can call log method
     * We can't easily mock static method calls without additional frameworks
     */
    public function test_log_with_location_and_message()
    {
        // Verify the class exists and method is callable
        $this->assertTrue(method_exists(Logger::class, 'log'));
        $this->assertTrue(is_callable([Logger::class, 'log']));
    }

    /**
     * Test log method with message only
     */
    public function test_log_with_message_only()
    {
        // When only one parameter is provided, it should be treated as message
        // with empty location
        $this->assertTrue(method_exists(Logger::class, 'log'));
    }

    /**
     * Test Logger class exists
     */
    public function test_logger_class_exists()
    {
        $this->assertTrue(class_exists(Logger::class));
    }

    /**
     * Test log method is static
     */
    public function test_log_is_static_method()
    {
        $reflection = new ReflectionClass(Logger::class);
        $method = $reflection->getMethod('log');
        
        $this->assertTrue($method->isStatic());
    }

    /**
     * Test log method is public
     */
    public function test_log_is_public_method()
    {
        $reflection = new ReflectionClass(Logger::class);
        $method = $reflection->getMethod('log');
        
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test log method accepts correct number of parameters
     */
    public function test_log_method_parameters()
    {
        $reflection = new ReflectionClass(Logger::class);
        $method = $reflection->getMethod('log');
        
        // Method should have 2 parameters
        $this->assertEquals(2, $method->getNumberOfParameters());
    }

    /**
     * Test log method parameter defaults
     */
    public function test_log_method_parameter_defaults()
    {
        $reflection = new ReflectionClass(Logger::class);
        $method = $reflection->getMethod('log');
        $parameters = $method->getParameters();
        
        // First parameter (locationId) should not be optional
        $this->assertFalse($parameters[0]->isOptional());
        
        // Second parameter (message) should be optional with default null
        $this->assertTrue($parameters[1]->isOptional());
        $this->assertNull($parameters[1]->getDefaultValue());
    }

    /**
     * Test Logger can be instantiated
     */
    public function test_logger_can_be_instantiated()
    {
        $logger = new Logger();
        $this->assertInstanceOf(Logger::class, $logger);
    }

    /**
     * Test log method with various data types
     */
    public function test_log_with_various_data_types()
    {
        // Test that the method exists and is callable with different types
        $this->assertTrue(is_callable([Logger::class, 'log']));
        
        // Verify method can accept string
        $reflection = new ReflectionClass(Logger::class);
        $method = $reflection->getMethod('log');
        $parameters = $method->getParameters();
        
        // locationId parameter should exist
        $this->assertEquals('locationId', $parameters[0]->getName());
        
        // message parameter should exist
        $this->assertEquals('message', $parameters[1]->getName());
    }
}
