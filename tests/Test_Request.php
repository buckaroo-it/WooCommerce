<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Services\Request;
use PHPUnit\Framework\TestCase;

/**
 * Test Request service class
 */
class Test_Request extends TestCase
{
    /**
     * Request instance
     *
     * @var Request
     */
    private $request;

    /**
     * Set up test fixtures
     */
    public function setUp(): void
    {
        parent::setUp();

        // Clear superglobals before each test
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->request = Request::make();
    }

    /**
     * Tear down test fixtures
     */
    public function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];

        parent::tearDown();
    }

    /**
     * Test make method creates new instance
     */
    public function test_make_creates_instance()
    {
        $request = Request::make();
        $this->assertInstanceOf(Request::class, $request);
    }

    /**
     * Test all method merges GET and POST
     */
    public function test_all_merges_get_and_post()
    {
        $_GET = ['key1' => 'value1'];
        $_POST = ['key2' => 'value2'];

        $result = $this->request->all();

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key2', $result);
        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
    }

    /**
     * Test all method POST overrides GET
     */
    public function test_all_post_overrides_get()
    {
        $_GET = ['key' => 'get_value'];
        $_POST = ['key' => 'post_value'];

        $result = $this->request->all();

        $this->assertEquals('post_value', $result['key']);
    }

    /**
     * Test query method returns GET data
     */
    public function test_query_returns_get_data()
    {
        $_GET = ['test_key' => 'test_value'];

        $result = $this->request->query('test_key');

        $this->assertEquals('test_value', $result);
    }

    /**
     * Test query method returns default when key not found
     */
    public function test_query_returns_default_when_not_found()
    {
        $result = $this->request->query('nonexistent', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    /**
     * Test query method returns all GET when no key provided
     */
    public function test_query_returns_all_get_without_key()
    {
        $_GET = ['key1' => 'value1', 'key2' => 'value2'];

        $result = $this->request->query();

        $this->assertEquals($_GET, $result);
    }

    /**
     * Test post method returns POST data
     */
    public function test_post_returns_post_data()
    {
        $_POST = ['test_key' => 'test_value'];

        $result = $this->request->post('test_key');

        $this->assertEquals('test_value', $result);
    }

    /**
     * Test post method returns default when key not found
     */
    public function test_post_returns_default_when_not_found()
    {
        $result = $this->request->post('nonexistent', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    /**
     * Test post method returns all POST when no key provided
     */
    public function test_post_returns_all_post_without_key()
    {
        $_POST = ['key1' => 'value1', 'key2' => 'value2'];

        $result = $this->request->post();

        $this->assertEquals($_POST, $result);
    }

    /**
     * Test method returns request method
     */
    public function test_method_returns_request_method()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->request->method();

        $this->assertEquals('POST', $result);
    }

    /**
     * Test method returns GET as default
     */
    public function test_method_returns_get_as_default()
    {
        unset($_SERVER['REQUEST_METHOD']);

        $result = $this->request->method();

        $this->assertEquals('GET', $result);
    }

    /**
     * Test exists method with existing key
     */
    public function test_exists_returns_true_for_existing_key()
    {
        if (!function_exists('map_deep')) {
            function map_deep($value, $callback) {
                return is_array($value) ? array_map(function($item) use ($callback) {
                    return map_deep($item, $callback);
                }, $value) : call_user_func($callback, $value);
            }
        }
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) {
                return strip_tags($str);
            }
        }

        $_POST = ['existing_key' => 'value'];

        $result = $this->request->exists('existing_key');

        $this->assertTrue($result);
    }

    /**
     * Test exists method with non-existing key
     */
    public function test_exists_returns_false_for_non_existing_key()
    {
        $result = $this->request->exists('nonexistent');

        $this->assertFalse($result);
    }

    /**
     * Test exists method with multiple keys all exist
     */
    public function test_exists_returns_true_when_all_keys_exist()
    {
        $_POST = ['key1' => 'value1', 'key2' => 'value2'];

        $result = $this->request->exists(['key1', 'key2']);

        $this->assertTrue($result);
    }

    /**
     * Test exists method with multiple keys not all exist
     */
    public function test_exists_returns_false_when_not_all_keys_exist()
    {
        $_POST = ['key1' => 'value1'];

        $result = $this->request->exists(['key1', 'nonexistent']);

        $this->assertFalse($result);
    }

    /**
     * Test has method with existing non-empty value
     */
    public function test_has_returns_true_for_existing_value()
    {
        $_POST = ['key' => 'value'];

        $result = $this->request->has('key');

        $this->assertTrue($result);
    }

    /**
     * Test has method with empty string
     */
    public function test_has_returns_false_for_empty_string()
    {
        $_POST = ['key' => ''];

        $result = $this->request->has('key');

        $this->assertFalse($result);
    }

    /**
     * Test has method with null value
     */
    public function test_has_returns_false_for_null()
    {
        $_POST = ['key' => null];

        $result = $this->request->has('key');

        $this->assertFalse($result);
    }

    /**
     * Test only method returns specified keys
     */
    public function test_only_returns_specified_keys()
    {
        $_POST = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        $result = $this->request->only(['key1', 'key3']);

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key3', $result);
        $this->assertArrayNotHasKey('key2', $result);
    }

    /**
     * Test only method with variadic arguments
     */
    public function test_only_accepts_variadic_arguments()
    {
        $_POST = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        $result = $this->request->only('key1', 'key3');

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key3', $result);
        $this->assertArrayNotHasKey('key2', $result);
    }

    /**
     * Test except method excludes specified keys
     */
    public function test_except_excludes_specified_keys()
    {
        $_POST = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        $result = $this->request->except(['key2']);

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key3', $result);
        $this->assertArrayNotHasKey('key2', $result);
    }

    /**
     * Test except method with variadic arguments
     */
    public function test_except_accepts_variadic_arguments()
    {
        $_POST = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        $result = $this->request->except('key2');

        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key3', $result);
        $this->assertArrayNotHasKey('key2', $result);
    }

    /**
     * Test only method with non-existent keys
     */
    public function test_only_with_non_existent_keys()
    {
        $_POST = ['key1' => 'value1'];

        $result = $this->request->only(['nonexistent']);

        $this->assertEmpty($result);
    }

    /**
     * Test except method removes all keys
     */
    public function test_except_can_remove_all_keys()
    {
        $_POST = ['key1' => 'value1'];

        $result = $this->request->except(['key1']);

        $this->assertEmpty($result);
    }

    /**
     * Test has with array of keys all present
     */
    public function test_has_with_array_all_present()
    {
        $_POST = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];

        $result = $this->request->has(['key1', 'key2']);

        $this->assertTrue($result);
    }

    /**
     * Test has with array of keys not all present
     */
    public function test_has_with_array_not_all_present()
    {
        $_POST = [
            'key1' => 'value1',
            'key2' => ''
        ];

        $result = $this->request->has(['key1', 'key2']);

        $this->assertFalse($result);
    }
}
