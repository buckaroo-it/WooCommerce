<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Admin\PaymentMethodSettings;

/**
 * Credential settings of Buckaroo gateways in the WooCommerce REST API
 * payment_gateways routes.
 */
class Test_RestGatewaySettings extends WP_UnitTestCase
{
    private const CARD_OPTION = 'woocommerce_buckaroo_creditcard_settings';

    private const IDEAL_OPTION = 'woocommerce_buckaroo_ideal_settings';

    /** @var array<string, mixed> */
    private $cardSettings = [
        'enabled' => 'yes',
        'creditcardmethod' => 'encrypt',
        'creditcardpayauthorize' => 'pay',
        'hosted_fields_client_id' => 'client-id-fixture',
        'hosted_fields_client_secret' => 'client-secret-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        update_option(self::CARD_OPTION, $this->cardSettings);
        WC()->payment_gateways()->init();

        $userId = self::factory()->user->create(['role' => 'subscriber']);
        get_user_by('id', $userId)->add_cap('manage_woocommerce');
        wp_set_current_user($userId);
    }

    protected function tearDown(): void
    {
        wp_set_current_user(0);
        delete_option(self::CARD_OPTION);
        delete_option(self::IDEAL_OPTION);
        WC()->payment_gateways()->init();

        parent::tearDown();
    }

    /**
     * @dataProvider versions
     */
    public function test_card_gateway_response_has_no_client_credentials(string $version): void
    {
        $settings = $this->get("/wc/$version/payment_gateways/buckaroo_creditcard")['settings'];

        $this->assertSame('', $settings['hosted_fields_client_id']['value']);
        $this->assertSame('', $settings['hosted_fields_client_secret']['value']);
        $this->assertSame('encrypt', $settings['creditcardmethod']['value']);
        $this->assertSame('pay', $settings['creditcardpayauthorize']['value']);
    }

    /**
     * @dataProvider versions
     */
    public function test_gateway_list_has_no_client_credentials(string $version): void
    {
        $list = $this->get("/wc/$version/payment_gateways");

        $body = (string) wp_json_encode($list);
        $this->assertStringNotContainsString('client-id-fixture', $body);
        $this->assertStringNotContainsString('client-secret-fixture', $body);

        $card = current(array_filter($list, static function ($gateway) {
            return $gateway['id'] === 'buckaroo_creditcard';
        }));
        $this->assertSame('encrypt', $card['settings']['creditcardmethod']['value']);
    }

    public function test_master_settings_response_has_no_store_credentials(): void
    {
        $gateway = new PaymentMethodSettings();
        $gateway->settings['merchantkey'] = 'store-key-fixture';
        $gateway->settings['secretkey'] = 'secret-key-fixture';

        $response = (new WC_REST_Payment_Gateways_Controller())->prepare_item_for_response($gateway, new WP_REST_Request());
        $settings = $response->get_data()['settings'];

        $this->assertSame('', $settings['merchantkey']['value']);
        $this->assertSame('', $settings['secretkey']['value']);
        $this->assertSame('store-key-fixture', $gateway->settings['merchantkey']);
    }

    public function test_other_buckaroo_gateway_settings_are_unchanged(): void
    {
        update_option(self::IDEAL_OPTION, ['enabled' => 'yes', 'title' => 'iDEAL fixture title']);
        WC()->payment_gateways()->init();

        $gateway = $this->get('/wc/v3/payment_gateways/buckaroo_ideal');

        $this->assertSame('iDEAL fixture title', $gateway['title']);
    }

    public function test_unrelated_gateway_password_settings_are_unchanged(): void
    {
        $gateway = new class () extends WC_Payment_Gateway {
            public function __construct()
            {
                $this->id = 'third_party_fixture';
                $this->form_fields = [
                    'api_secret' => ['title' => 'Secret', 'type' => 'password'],
                ];
                $this->settings = ['api_secret' => 'third-party-secret-fixture'];
            }
        };
        $response = rest_ensure_response(['settings' => ['api_secret' => ['id' => 'api_secret', 'type' => 'password', 'value' => 'third-party-secret-fixture']]]);

        $filtered = apply_filters('woocommerce_rest_prepare_payment_gateway', $response, $gateway, new WP_REST_Request());

        $this->assertSame('third-party-secret-fixture', $filtered->get_data()['settings']['api_secret']['value']);
    }

    public function test_stored_credentials_are_kept_after_reading(): void
    {
        $this->get('/wc/v3/payment_gateways/buckaroo_creditcard');

        $stored = get_option(self::CARD_OPTION);
        $this->assertSame('client-id-fixture', $stored['hosted_fields_client_id']);
        $this->assertSame('client-secret-fixture', $stored['hosted_fields_client_secret']);

        $gateway = WC()->payment_gateways()->payment_gateways()['buckaroo_creditcard'];
        $this->assertSame('client-secret-fixture', $gateway->get_option('hosted_fields_client_secret'));
    }

    /**
     * @dataProvider versions
     */
    public function test_update_with_blank_credentials_keeps_stored_credentials(string $version): void
    {
        $settings = $this->get("/wc/$version/payment_gateways/buckaroo_creditcard")['settings'];
        $values = array_map(static function ($setting) {
            return $setting['value'];
        }, $settings);
        $values['creditcardpayauthorize'] = 'authorize';

        $response = $this->send('PUT', "/wc/$version/payment_gateways/buckaroo_creditcard", ['settings' => $values]);

        $this->assertSame(200, $response->get_status());
        $stored = get_option(self::CARD_OPTION);
        $this->assertSame('authorize', $stored['creditcardpayauthorize']);
        $this->assertSame('client-id-fixture', $stored['hosted_fields_client_id']);
        $this->assertSame('client-secret-fixture', $stored['hosted_fields_client_secret']);
        $this->assertSame('', $response->get_data()['settings']['hosted_fields_client_secret']['value']);
    }

    public function test_update_with_new_credentials_saves_them(): void
    {
        $response = $this->send('PUT', '/wc/v3/payment_gateways/buckaroo_creditcard', [
            'settings' => ['hosted_fields_client_secret' => 'new-secret-fixture'],
        ]);

        $this->assertSame(200, $response->get_status());
        $stored = get_option(self::CARD_OPTION);
        $this->assertSame('new-secret-fixture', $stored['hosted_fields_client_secret']);
        $this->assertSame('client-id-fixture', $stored['hosted_fields_client_id']);
    }

    public function versions(): array
    {
        return ['v2' => ['v2'], 'v3' => ['v3']];
    }

    private function get(string $route): array
    {
        $response = $this->send('GET', $route);
        $this->assertSame(200, $response->get_status(), $route);

        return $response->get_data();
    }

    private function send(string $method, string $route, array $body = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, $route);
        if ($body !== []) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) wp_json_encode($body));
        }

        return rest_get_server()->dispatch($request);
    }
}
