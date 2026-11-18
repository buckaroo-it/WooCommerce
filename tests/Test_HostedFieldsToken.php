<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Gateways\CreditCard\HostedFieldsController;

/**
 * Hosted Fields token endpoint: who gets a token, caching in the customer
 * session, limits on new tokens, responses on Buckaroo errors and the URL the
 * checkout calls.
 */
class Test_HostedFieldsToken extends WP_UnitTestCase
{
    private const OPTION = 'woocommerce_buckaroo_creditcard_settings';

    /** @var array<string, mixed> */
    private $server = [];

    /** @var array<string, mixed> */
    private $get = [];

    /** @var array<string, mixed> */
    private $post = [];

    /** @var mixed */
    private $originalSession;

    /** @var int */
    private $productId = 0;

    /** @var array<int, array<string, mixed>> */
    private $authRequests = [];

    /** @var callable(): (array|WP_Error) */
    private $authResponse;

    /** @var int */
    private $issued = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $this->get = $_GET;
        $this->post = $_POST;
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.' . wp_rand(1, 254);

        if (! WC()->session) {
            WC()->initialize_session();
        }
        if (! WC()->customer) {
            WC()->customer = new WC_Customer(0, true);
        }
        if (! WC()->cart) {
            WC()->initialize_cart();
        }
        $this->originalSession = WC()->session;
        WC()->cart->empty_cart(false);

        update_option('woocommerce_currency', 'EUR');
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        $this->configureGateway([]);

        $product = new WC_Product_Simple();
        $product->set_name('Hosted fields test product');
        $product->set_regular_price('25.00');
        $this->productId = $product->save();

        $this->authResponse = function () {
            $this->issued++;

            return $this->httpResponse(200, ['access_token' => 'token-' . $this->issued, 'expires_in' => 600]);
        };
        add_filter('pre_http_request', [$this, 'interceptHttp'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'interceptHttp'], 10);
        WC()->cart->empty_cart(false);
        WC()->session = $this->originalSession;
        wp_delete_post($this->productId, true);
        delete_option(self::OPTION);
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        WC()->payment_gateways()->init();
        $_SERVER = $this->server;
        $_GET = $this->get;
        $_POST = $this->post;
        wc_clear_notices();
        wp_set_current_user(0);

        parent::tearDown();
    }

    /**
     * @param  false|array|WP_Error  $preempt
     * @return false|array|WP_Error
     */
    public function interceptHttp($preempt, $args, $url)
    {
        if ($url !== HostedFieldsController::AUTH_URL) {
            return $preempt;
        }

        $this->authRequests[] = $args;

        return ($this->authResponse)();
    }

    public function test_request_without_customer_session_is_rejected(): void
    {
        $this->addToCart();
        $this->useSession(false);

        $result = $this->request();

        $this->assertSame(403, $result['status']);
        $this->assertArrayNotHasKey('access_token', $result['body']);
        $this->assertCount(0, $this->authRequests);
    }

    public function test_guest_with_session_and_cart_gets_a_token(): void
    {
        $this->addToCart();
        $this->useSession();

        $result = $this->request();

        $this->assertSame(200, $result['status']);
        $this->assertSame('token-1', $result['body']['access_token']);
        $this->assertSame(600 - HostedFieldsController::EXPIRY_MARGIN, $result['body']['expires_in']);
        $this->assertCount(1, $this->authRequests);
        $this->assertSame(
            'Basic ' . base64_encode('client-id-fixture:client-secret-fixture'),
            $this->authRequests[0]['headers']['Authorization']
        );
        $this->assertStringContainsString('scope=hostedfields%3Asave', $this->authRequests[0]['body']);
    }

    public function test_logged_in_customer_with_cart_gets_a_token(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));
        $this->addToCart();
        $this->useSession();

        $result = $this->request();

        $this->assertSame(200, $result['status']);
        $this->assertSame('token-1', $result['body']['access_token']);
    }

    public function test_session_with_empty_cart_is_rejected(): void
    {
        $this->useSession();

        $result = $this->request();

        $this->assertSame(403, $result['status']);
        $this->assertCount(0, $this->authRequests);
    }

    public function test_request_without_valid_nonce_is_rejected(): void
    {
        $this->addToCart();
        $this->useSession();

        foreach ([null, '', 'invalid'] as $nonce) {
            $result = $this->request($nonce);

            $this->assertSame(403, $result['status']);
        }
        $this->assertCount(0, $this->authRequests);
    }

    public function test_redirect_mode_keeps_the_uses_redirect_reply(): void
    {
        $this->configureGateway(['creditcardmethod' => 'redirect']);
        $this->addToCart();
        $this->useSession();

        $result = $this->request();

        $this->assertSame(200, $result['status']);
        $this->assertSame(['error' => 'uses_redirect'], $result['body']);
        $this->assertCount(0, $this->authRequests);
    }

    public function test_disabled_gateway_is_rejected(): void
    {
        $this->configureGateway(['enabled' => 'no']);
        $this->addToCart();
        $this->useSession();

        $result = $this->request();

        $this->assertSame(403, $result['status']);
        $this->assertCount(0, $this->authRequests);
    }

    public function test_gateway_not_available_for_the_cart_is_rejected(): void
    {
        $this->configureGateway(['maxvalue' => '10']);
        $this->addToCart();
        $this->useSession();

        $result = $this->request();

        $this->assertSame(403, $result['status']);
        $this->assertCount(0, $this->authRequests);
    }

    public function test_gateway_removed_from_available_gateways_is_rejected(): void
    {
        $this->addToCart();
        $this->useSession();
        $remove = static function ($gateways) {
            unset($gateways['buckaroo_creditcard']);

            return $gateways;
        };
        add_filter('woocommerce_available_payment_gateways', $remove, 99);

        try {
            $result = $this->request();
        } finally {
            remove_filter('woocommerce_available_payment_gateways', $remove, 99);
        }

        $this->assertSame(403, $result['status']);
        $this->assertCount(0, $this->authRequests);
    }

    public function test_missing_client_credentials_give_a_generic_error(): void
    {
        $this->configureGateway(['hosted_fields_client_secret' => '']);
        $this->addToCart();
        $this->useSession();

        $result = $this->request();

        $this->assertSame(503, $result['status']);
        $this->assertSame($this->genericError(), $result['body']);
        $this->assertCount(0, $this->authRequests);
    }

    public function test_token_is_reused_within_its_lifetime(): void
    {
        $this->addToCart();
        $this->useSession();

        $first = $this->request();
        $second = $this->request();

        $this->assertSame('token-1', $first['body']['access_token']);
        $this->assertSame('token-1', $second['body']['access_token']);
        $this->assertLessThanOrEqual($first['body']['expires_in'], $second['body']['expires_in']);
        $this->assertCount(1, $this->authRequests);
    }

    public function test_new_token_is_issued_after_card_details_are_submitted(): void
    {
        $this->addToCart();
        $this->useSession();
        $first = $this->request();

        $_POST = [
            'buckaroo_creditcard-creditcard-issuer' => 'visa',
            'buckaroo_creditcard-encrypted-data' => 'hosted-fields-session-fixture',
        ];
        (new CreditCardGateway())->validate_fields();

        $second = $this->request();

        $this->assertSame('token-1', $first['body']['access_token']);
        $this->assertSame('token-2', $second['body']['access_token']);
        $this->assertCount(2, $this->authRequests);
    }

    public function test_token_is_kept_when_card_form_was_not_submitted(): void
    {
        $this->addToCart();
        $this->useSession();
        $this->request();

        $_POST = ['buckaroo_creditcard-creditcard-issuer' => 'visa'];
        (new CreditCardGateway())->validate_fields();

        $result = $this->request();

        $this->assertSame('token-1', $result['body']['access_token']);
        $this->assertCount(1, $this->authRequests);
    }

    public function test_token_close_to_expiry_is_replaced(): void
    {
        $this->addToCart();
        $session = $this->useSession();
        $this->request();

        $cached = $session->get(HostedFieldsController::SESSION_TOKEN_KEY);
        $cached['expires_at'] = time() + HostedFieldsController::MIN_REMAINING_LIFETIME - 1;
        $session->set(HostedFieldsController::SESSION_TOKEN_KEY, $cached);

        $result = $this->request();

        $this->assertSame('token-2', $result['body']['access_token']);
        $this->assertCount(2, $this->authRequests);
    }

    public function test_token_is_replaced_after_client_id_changes(): void
    {
        $this->addToCart();
        $this->useSession();
        $this->request();

        $this->configureGateway(['hosted_fields_client_id' => 'other-client-id-fixture']);
        $result = $this->request();

        $this->assertSame('token-2', $result['body']['access_token']);
        $this->assertCount(2, $this->authRequests);
    }

    public function test_tokens_are_not_shared_between_sessions(): void
    {
        $this->addToCart();

        $this->useSession();
        $first = $this->request();
        $this->useSession();
        $second = $this->request();

        $this->assertSame('token-1', $first['body']['access_token']);
        $this->assertSame('token-2', $second['body']['access_token']);
        $this->assertCount(2, $this->authRequests);
    }

    public function test_new_tokens_per_session_are_limited(): void
    {
        $this->useShortLivedTokens();
        $this->addToCart();
        $this->useSession();

        for ($i = 1; $i <= HostedFieldsController::SESSION_LIMIT; $i++) {
            $this->assertSame(200, $this->request()['status'], "Request $i");
        }
        $result = $this->request();

        $this->assertSame(429, $result['status']);
        $this->assertSame($this->genericError(), $result['body']);
        $this->assertCount(HostedFieldsController::SESSION_LIMIT, $this->authRequests);
    }

    public function test_new_tokens_per_client_address_are_limited_across_sessions(): void
    {
        $this->addToCart();

        for ($i = 1; $i <= HostedFieldsController::CLIENT_LIMIT; $i++) {
            $this->useSession();
            $this->assertSame(200, $this->request()['status'], "Request $i");
        }
        $this->useSession();
        $result = $this->request();

        $this->assertSame(429, $result['status']);
        $this->assertCount(HostedFieldsController::CLIENT_LIMIT, $this->authRequests);

        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $this->assertSame(200, $this->request()['status']);
    }

    public function test_cached_tokens_do_not_count_towards_the_limit(): void
    {
        $this->addToCart();
        $this->useSession();

        for ($i = 0; $i < HostedFieldsController::SESSION_LIMIT * 2; $i++) {
            $this->assertSame(200, $this->request()['status']);
        }

        $this->assertCount(1, $this->authRequests);
    }

    /**
     * @dataProvider failedAuthResponses
     */
    public function test_buckaroo_errors_give_a_generic_error(callable $response, string $hiddenText): void
    {
        $this->authResponse = $response;
        $this->addToCart();
        $session = $this->useSession();

        $result = $this->request();

        $this->assertSame(502, $result['status']);
        $this->assertSame($this->genericError(), $result['body']);
        $this->assertStringNotContainsString($hiddenText, (string) wp_json_encode($result['body']));
        $this->assertNull($session->get(HostedFieldsController::SESSION_TOKEN_KEY));
    }

    public function failedAuthResponses(): array
    {
        return [
            'rejected credentials' => [
                function () {
                    return $this->httpResponse(401, ['error' => 'invalid_client', 'error_message' => 'upstream-detail-fixture']);
                },
                'upstream-detail-fixture',
            ],
            'transport error' => [
                static function () {
                    return new WP_Error('http_request_failed', 'cURL error 28: upstream-detail-fixture timed out');
                },
                'upstream-detail-fixture',
            ],
            'not json' => [
                function () {
                    return ['headers' => [], 'body' => '<html>upstream-detail-fixture</html>', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
                },
                'upstream-detail-fixture',
            ],
            'no token' => [
                function () {
                    return $this->httpResponse(200, ['error_message' => 'upstream-detail-fixture']);
                },
                'upstream-detail-fixture',
            ],
            'no lifetime' => [
                function () {
                    return $this->httpResponse(200, ['access_token' => 'upstream-detail-fixture']);
                },
                'upstream-detail-fixture',
            ],
        ];
    }

    public function test_token_url_at_domain_root(): void
    {
        update_option('home', 'https://example.org');
        $this->set_permalink_structure('/%postname%/');

        $url = HostedFieldsController::tokenUrl();

        $this->assertStringStartsWith('https://example.org/wc-api/WC_Gateway_Buckaroo_creditcard-hosted-fields-token/?_wpnonce=', $url);
    }

    public function test_token_url_in_subdirectory(): void
    {
        update_option('home', 'https://example.org/shop');
        $this->set_permalink_structure('/%postname%/');

        $url = HostedFieldsController::tokenUrl();

        $this->assertStringStartsWith('https://example.org/shop/wc-api/WC_Gateway_Buckaroo_creditcard-hosted-fields-token/?_wpnonce=', $url);
    }

    public function test_token_url_in_subdirectory_with_plain_permalinks(): void
    {
        update_option('home', 'https://example.org/shop');
        $this->set_permalink_structure('');

        $url = HostedFieldsController::tokenUrl();

        $this->assertStringStartsWith('https://example.org/shop/?', $url);
        $this->assertStringContainsString('wc-api=WC_Gateway_Buckaroo_creditcard-hosted-fields-token', $url);
        $this->assertStringContainsString('_wpnonce=', $url);
    }

    public function test_token_url_nonce_is_accepted_by_the_endpoint(): void
    {
        $this->addToCart();
        $this->useSession();
        parse_str((string) wp_parse_url(HostedFieldsController::tokenUrl(), PHP_URL_QUERY), $query);

        $result = $this->request($query['_wpnonce']);

        $this->assertSame(200, $result['status']);
    }

    public function test_endpoint_is_registered_for_the_wc_api_request(): void
    {
        $this->assertNotFalse(has_action('woocommerce_api_' . strtolower(HostedFieldsController::API_REQUEST)));
    }

    private function request(?string $nonce = 'valid'): array
    {
        $_GET = [];
        if ($nonce === 'valid') {
            $_GET['_wpnonce'] = wp_create_nonce(HostedFieldsController::NONCE_ACTION);
        } elseif ($nonce !== null) {
            $_GET['_wpnonce'] = $nonce;
        }

        return HostedFieldsController::handleRequest();
    }

    private function configureGateway(array $overrides): void
    {
        update_option(self::OPTION, array_merge([
            'enabled' => 'yes',
            'mode' => 'test',
            'creditcardmethod' => 'encrypt',
            'AllowedProvider' => ['visa', 'mastercard'],
            'hosted_fields_client_id' => 'client-id-fixture',
            'hosted_fields_client_secret' => 'client-secret-fixture',
        ], $overrides));
        WC()->payment_gateways()->init();
    }

    private function addToCart(): void
    {
        WC()->cart->add_to_cart($this->productId, 1);
        WC()->cart->calculate_totals();
    }

    /**
     * Give the request a fresh customer session.
     */
    private function useSession(bool $active = true): WC_Session_Handler
    {
        $session = new class () extends WC_Session_Handler {
            /** @var bool */
            public $active = true;

            public function has_session()
            {
                return $this->active;
            }
        };
        $session->active = $active;
        WC()->session = $session;

        return $session;
    }

    private function useShortLivedTokens(): void
    {
        $this->authResponse = function () {
            $this->issued++;

            return $this->httpResponse(200, [
                'access_token' => 'token-' . $this->issued,
                'expires_in' => HostedFieldsController::MIN_REMAINING_LIFETIME - 1,
            ]);
        };
    }

    private function httpResponse(int $code, array $body): array
    {
        return [
            'headers' => [],
            'body' => (string) wp_json_encode($body),
            'response' => ['code' => $code, 'message' => ''],
            'cookies' => [],
            'filename' => null,
        ];
    }

    private function genericError(): array
    {
        return ['error' => 'The card form could not be loaded. Please try again or choose another payment method.'];
    }
}
