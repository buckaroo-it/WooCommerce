<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
use Buckaroo\Woocommerce\Services\LoggerStorage;
use Buckaroo\Woocommerce\Services\Request;

class Test_RequestInputHandling extends WP_UnitTestCase
{
    /** @var array<string, mixed> */
    private $server;

    /** @var array<string, mixed> */
    private $post;

    /** @var array<string, mixed> */
    private $get;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $this->post = $_POST;
        $this->get = $_GET;
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR', 'CONTENT_TYPE'] as $key) {
            unset($_SERVER[$key]);
        }
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_POST = $this->post;
        $_GET = $this->get;

        parent::tearDown();
    }

    public function test_client_ip_is_the_remote_address_of_a_public_client(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.8';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.4';

        $this->assertSame('198.51.100.4', $this->clientIp());
    }

    public function test_client_ip_of_an_ipv4_mapped_public_client_is_the_remote_address(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99';
        $_SERVER['REMOTE_ADDR'] = '::ffff:198.51.100.4';

        $this->assertSame('198.51.100.4', $this->clientIp());
    }

    public function test_client_ip_behind_a_private_proxy_uses_the_nearest_forwarded_address(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.1, 203.0.113.7, 10.0.0.2';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $this->assertSame('203.0.113.7', $this->clientIp());
    }

    public function test_client_ip_behind_a_filtered_proxy_uses_the_forwarded_address(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        $trust = static function () {
            return ['198.51.100.9'];
        };
        add_filter('buckaroo_trusted_proxies', $trust);

        try {
            $this->assertSame('203.0.113.7', $this->clientIp());
        } finally {
            remove_filter('buckaroo_trusted_proxies', $trust);
        }
    }

    public function test_client_ip_behind_a_proxy_stops_at_an_invalid_forwarded_entry(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, unknown';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $this->assertSame('10.0.0.1', $this->clientIp());
    }

    public function test_client_ip_falls_back_to_the_remote_address(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.4';

        $this->assertSame('198.51.100.4', $this->clientIp());
    }

    public function test_client_ip_is_empty_when_the_remote_address_is_not_an_ip_address(): void
    {
        $_SERVER['REMOTE_ADDR'] = '<b>not-an-ip</b>';

        $this->assertSame('', $this->clientIp());
    }

    public function test_client_ip_is_empty_without_any_address(): void
    {
        $this->assertSame('', $this->clientIp());
    }

    public function test_buckaroo_reply_values_are_kept_as_sent(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = wp_slash([
            'brq_statuscode' => '190',
            'brq_statusmessage' => 'Transaction successfully processed <ok> & "done"',
            'brq_invoicenumber' => "O'Brien-1",
        ]);

        $response = ResponseRegistry::getResponseFromRequest();

        $this->assertSame('Transaction successfully processed <ok> & "done"', $response->get('brq_statusmessage', null, false));
        $this->assertSame("O'Brien-1", $response->get('brq_invoicenumber', null, false));
    }

    public function test_buckaroo_reply_is_read_from_the_query_string_for_get_requests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['brq_statuscode' => '190'];
        $_POST = ['brq_statuscode' => '490'];

        $this->assertSame('190', ResponseRegistry::getResponseFromRequest()->get('brq_statuscode', null, false));
    }

    public function test_request_query_and_post_values_are_unslashed_and_sanitized(): void
    {
        $_GET = wp_slash(['name' => "O'Brien <b>x</b>"]);
        $_POST = wp_slash(['name' => "D'Arcy"]);

        $this->assertSame("O'Brien x", Request::make()->query('name'));
        $this->assertSame("D'Arcy", Request::make()->post('name'));
    }

    public function test_request_input_reads_unslashed_posted_values(): void
    {
        $_GET = ['name' => 'from-query'];
        $_POST = wp_slash(['name' => "O'Brien <b>x</b>"]);

        $this->assertSame("O'Brien x", Request::make()->input('name'));
    }

    public function test_request_input_keeps_store_api_values_as_sent(): void
    {
        $_POST = ['paymentdata' => '{"signedMessage":"{\\"a\\":1}"}', 'name' => "O\\'Brien"];
        $request = new class extends Request {
            protected function isRestRequest(): bool
            {
                return true;
            }
        };

        $this->assertSame('{"signedMessage":"{\\"a\\":1}"}', $request->input('paymentdata'));
        $this->assertSame("O\\'Brien", $request->input('name'));
    }

    public function test_request_input_ignores_query_values(): void
    {
        $_GET = ['name' => 'from-query'];
        $_POST = [];

        $this->assertNull(Request::make()->input('name'));
        $this->assertSame('fallback', Request::make()->input('name', 'fallback'));
    }

    public function test_request_input_returns_the_default_for_a_list_value(): void
    {
        $_POST = ['iban' => ['NL91ABNA0417164300']];

        $this->assertSame('', Request::make()->input('iban', ''));
    }

    public function test_request_input_casts_single_values_to_text(): void
    {
        $_POST = ['amount' => 10, 'accept' => true];

        $this->assertSame('10', Request::make()->input('amount'));
        $this->assertSame('1', Request::make()->input('accept'));
    }

    public function test_request_method_ignores_markup(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST<script>';

        $this->assertSame('POST', Request::make()->method());
    }

    public function test_request_context_keeps_percent_encoded_path(): void
    {
        $_SERVER['REQUEST_URI'] = '/product/caf%C3%A9/?x=1';

        $this->assertSame('/product/caf%C3%A9/', LoggerStorage::request_context()['REQUEST_PATH']);
    }

    private function clientIp(): string
    {
        $processor = new class extends AbstractProcessor {
            public function getAction(): string
            {
                return 'pay';
            }

            public function clientIp(): string
            {
                return $this->getIp();
            }
        };

        return $processor->clientIp();
    }
}
