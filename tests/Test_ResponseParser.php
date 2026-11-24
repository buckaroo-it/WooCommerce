<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\ResponseParser\FormDataParser;
use Buckaroo\Woocommerce\ResponseParser\JsonParser;
use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;
use PHPUnit\Framework\TestCase;

/**
 * Test ResponseParser abstract class through its concrete implementations
 */
class Test_ResponseParser extends TestCase
{
    /**
     * Test make method returns FormDataParser for Buckaroo response
     */
    public function test_make_returns_form_data_parser_for_buckaroo_response()
    {
        $data = [
            'brq_statuscode' => '190',
            'brq_amount' => '100.00'
        ];

        $parser = ResponseParser::make($data);

        $this->assertInstanceOf(FormDataParser::class, $parser);
    }

    /**
     * Test make method returns JsonParser for non-Buckaroo response
     */
    public function test_make_returns_json_parser_for_non_buckaroo_response()
    {
        $data = [
            'status' => 'success',
            'amount' => '100.00'
        ];

        $parser = ResponseParser::make($data);

        $this->assertInstanceOf(JsonParser::class, $parser);
    }

    /**
     * Test make method returns JsonParser for empty array
     */
    public function test_make_returns_json_parser_for_empty_array()
    {
        $parser = ResponseParser::make([]);

        $this->assertInstanceOf(JsonParser::class, $parser);
    }

    /**
     * Test get method returns value
     */
    public function test_get_returns_value()
    {
        $data = ['key' => 'value'];
        $parser = new JsonParser($data);

        $result = $parser->get('key');

        $this->assertEquals('value', $result);
    }

    /**
     * Test get method returns default for non-existing key
     */
    public function test_get_returns_default_for_non_existing_key()
    {
        $parser = new JsonParser([]);

        $result = $parser->get('nonexistent', 'default');

        $this->assertEquals('default', $result);
    }

    /**
     * Test get method returns all data when no key provided
     */
    public function test_get_returns_all_data_without_key()
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $parser = new JsonParser($data);

        $result = $parser->get();

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key2', $result);
    }

    /**
     * Test get method is case insensitive
     */
    public function test_get_is_case_insensitive()
    {
        $data = ['Key' => 'value'];
        $parser = new JsonParser($data);

        $result = $parser->get('key');

        $this->assertEquals('value', $result);
    }

    /**
     * Test get method with dot notation
     */
    public function test_get_with_dot_notation()
    {
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => 'value'
                ]
            ]
        ];
        $parser = new JsonParser($data);

        $result = $parser->get('level1.level2.level3');

        $this->assertEquals('value', $result);
    }

    /**
     * Test get method with dot notation non-existing key
     */
    public function test_get_with_dot_notation_non_existing()
    {
        $data = ['level1' => ['level2' => 'value']];
        $parser = new JsonParser($data);

        $result = $parser->get('level1.nonexistent', 'default');

        $this->assertEquals('default', $result);
    }

    /**
     * Test set method sets value
     */
    public function test_set_sets_value()
    {
        $parser = new JsonParser([]);

        $parser->set('key', 'value');

        $this->assertEquals('value', $parser->get('key'));
    }

    /**
     * Test set method with dot notation
     */
    public function test_set_with_dot_notation()
    {
        $parser = new JsonParser([]);

        $parser->set('level1.level2', 'value');

        $this->assertEquals('value', $parser->get('level1.level2'));
    }

    /**
     * Test set method overwrites existing value
     */
    public function test_set_overwrites_existing_value()
    {
        $parser = new JsonParser(['key' => 'old_value']);

        $parser->set('key', 'new_value');

        $this->assertEquals('new_value', $parser->get('key'));
    }

    /**
     * Test firstWhere method finds matching item
     */
    public function test_first_where_finds_matching_item()
    {
        $parser = new JsonParser([]);
        
        $array = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
            ['id' => 3, 'name' => 'Bob']
        ];

        $result = $parser->firstWhere($array, 'id', 2);

        $this->assertEquals(['id' => 2, 'name' => 'Jane'], $result);
    }

    /**
     * Test firstWhere method returns null when not found
     */
    public function test_first_where_returns_null_when_not_found()
    {
        $parser = new JsonParser([]);
        
        $array = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $result = $parser->firstWhere($array, 'id', 999);

        $this->assertNull($result);
    }

    /**
     * Test firstWhere method with non-array input
     */
    public function test_first_where_with_non_array()
    {
        $parser = new JsonParser([]);

        $result = $parser->firstWhere('not_an_array', 'key', 'value');

        $this->assertNull($result);
    }

    /**
     * Test normalizeItems converts keys to lowercase
     */
    public function test_normalize_items_converts_keys_to_lowercase()
    {
        $data = ['KEY' => 'value', 'AnotherKey' => 'value2'];
        $parser = new JsonParser($data);

        $result = $parser->get();

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('anotherkey', $result);
    }

    /**
     * Test normalizeItems handles nested arrays
     */
    public function test_normalize_items_handles_nested_arrays()
    {
        $data = [
            'LEVEL1' => [
                'LEVEL2' => 'value'
            ]
        ];
        $parser = new JsonParser($data);

        $result = $parser->get('level1.level2');

        $this->assertEquals('value', $result);
    }

    /**
     * Test formatAmount with valid numeric value
     */
    public function test_format_amount_with_numeric_value()
    {
        $parser = new JsonParser([]);
        
        // Use reflection to access protected method
        $reflection = new ReflectionClass($parser);
        $method = $reflection->getMethod('formatAmount');
        $method->setAccessible(true);

        $result = $method->invoke($parser, '100.50');

        $this->assertIsFloat($result);
        $this->assertEquals(100.50, $result);
    }

    /**
     * Test formatAmount with integer
     */
    public function test_format_amount_with_integer()
    {
        $parser = new JsonParser([]);
        
        $reflection = new ReflectionClass($parser);
        $method = $reflection->getMethod('formatAmount');
        $method->setAccessible(true);

        $result = $method->invoke($parser, 100);

        $this->assertIsFloat($result);
        $this->assertEquals(100.0, $result);
    }

    /**
     * Test formatAmount with non-numeric value
     */
    public function test_format_amount_with_non_numeric_value()
    {
        $parser = new JsonParser([]);
        
        $reflection = new ReflectionClass($parser);
        $method = $reflection->getMethod('formatAmount');
        $method->setAccessible(true);

        $result = $method->invoke($parser, 'not_a_number');

        $this->assertNull($result);
    }

    /**
     * Test formatAmount with null
     */
    public function test_format_amount_with_null()
    {
        $parser = new JsonParser([]);
        
        $reflection = new ReflectionClass($parser);
        $method = $reflection->getMethod('formatAmount');
        $method->setAccessible(true);

        $result = $method->invoke($parser, null);

        $this->assertNull($result);
    }

    /**
     * Test set method is case insensitive
     */
    public function test_set_is_case_insensitive()
    {
        $parser = new JsonParser([]);

        $parser->set('KEY', 'value');

        $this->assertEquals('value', $parser->get('key'));
        $this->assertEquals('value', $parser->get('KEY'));
    }

    /**
     * Test get method with nested arrays preserves case in values
     */
    public function test_get_preserves_value_case()
    {
        $data = ['key' => 'MixedCaseValue'];
        $parser = new JsonParser($data);

        $result = $parser->get('key');

        $this->assertEquals('MixedCaseValue', $result);
    }

    /**
     * Test getServiceParameter matches a JSON push parameter name in any case
     *
     * @dataProvider paypalPushParameters
     */
    public function test_get_service_parameter_matches_the_name_in_any_case(string $name, ?string $expected)
    {
        $parser = ResponseParser::make(json_decode(
            file_get_contents(__DIR__ . '/fixtures/paypal-express-json-push.json'),
            true
        ));

        $this->assertInstanceOf(JsonParser::class, $parser);
        $this->assertSame($expected, $parser->getServiceParameter($name));
    }

    public function paypalPushParameters(): array
    {
        return [
            'camelCase' => ['payerFirstname', 'Jan'],
            'snake_case' => ['address_line_1', 'Teststraat 1'],
            'PascalCase' => ['CustomerName', 'Jan Jansen'],
            'camelCase asked in PascalCase' => ['PayerLastname', 'Jansen'],
            'snake_case asked in upper case' => ['POSTAL_CODE', '1234 AB'],
            'PascalCase asked in camelCase' => ['customerName', 'Jan Jansen'],
            'missing parameter' => ['admin_area_1', null],
        ];
    }

    /**
     * Test getServiceParameter reads the parameter from the named service
     */
    public function test_get_service_parameter_reads_the_named_service()
    {
        $parser = ResponseParser::make([
            'Transaction' => [
                'ServiceCode' => 'afterpay',
                'Services' => [
                    [
                        'Name' => 'afterpay',
                        'Parameters' => [['Name' => 'ErrorResponseMessage', 'Value' => 'Address not valid']],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Address not valid', $parser->getServiceParameter('ErrorResponseMessage', 'afterpay'));
        $this->assertNull($parser->getServiceParameter('ErrorResponseMessage', 'klarnakp'));
    }

    /**
     * Test getServiceParameter reads the payment method's service by default
     */
    public function test_get_service_parameter_reads_the_payment_method_service()
    {
        $parser = ResponseParser::make([
            'Transaction' => [
                'ServiceCode' => 'klarnakp',
                'Services' => [
                    [
                        'Name' => 'klarnakp',
                        'Parameters' => [['Name' => 'ReservationNumber', 'Value' => 'a1b2c3d4-e5f6']],
                    ],
                ],
            ],
        ]);

        $this->assertSame('a1b2c3d4-e5f6', $parser->getServiceParameter('reservationNumber'));
    }

    /**
     * Test getServiceParameter returns null when the push has no services
     */
    public function test_get_service_parameter_is_null_without_services()
    {
        $parser = ResponseParser::make(['Transaction' => ['ServiceCode' => 'paypal', 'Services' => null]]);

        $this->assertNull($parser->getServiceParameter('payerFirstname'));
    }
}
