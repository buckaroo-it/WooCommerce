<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
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

    public function test_client_ip_uses_the_first_address_of_the_forwarded_list(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $this->assertSame('203.0.113.7', $this->clientIp());
    }

    public function test_client_ip_falls_back_to_the_remote_address(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.4';

        $this->assertSame('198.51.100.4', $this->clientIp());
    }

    public function test_client_ip_is_empty_when_the_header_is_not_an_ip_address(): void
    {
        $_SERVER['HTTP_CLIENT_IP'] = '<b>not-an-ip</b>';

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

    public function test_request_method_ignores_markup(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST<script>';

        $this->assertSame('POST', Request::make()->method());
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
