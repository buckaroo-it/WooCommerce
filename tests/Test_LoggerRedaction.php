<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Admin\GeneralSettings;
use Buckaroo\Woocommerce\Admin\PaymentMethodSettings;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\LoggerStorage;

class Test_LoggerRedaction extends WP_UnitTestCase
{
    private const MASTER_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    private $originalMaster;

    private $originalPost;

    protected function setUp(): void
    {
        global $wpdb;

        parent::setUp();
        $this->originalMaster = get_option(self::MASTER_OPTION, null);
        $this->originalPost = $_POST;

        $master = is_array($this->originalMaster) ? $this->originalMaster : [];
        $master['debugmode'] = 'on';
        $master['logstorage'] = LoggerStorage::STORAGE_DB;
        update_option(self::MASTER_OPTION, $master);

        $table = $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE;
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS $table (
                `id` BIGINT NOT NULL AUTO_INCREMENT ,
                `date` DATETIME NOT NULL ,
                `process_id` VARCHAR(23) NOT NULL ,
                `message` TEXT NOT NULL ,
                `location_id` VARCHAR(255) NOT NULL ,
                PRIMARY KEY (`id`)
            )"
        );
        $wpdb->query("DELETE FROM $table");
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;
        if ($this->originalMaster === null) {
            delete_option(self::MASTER_OPTION);
        } else {
            update_option(self::MASTER_OPTION, $this->originalMaster);
        }
        parent::tearDown();
    }

    public function test_saving_settings_with_debug_mode_does_not_log_credentials(): void
    {
        global $current_section;

        $previousSection = $current_section;
        $gateway = new PaymentMethodSettings();
        $_POST = [
            $gateway->get_field_key('secretkey') => 'secret-key-fixture',
            $gateway->get_field_key('merchantkey') => 'website-key-fixture',
            'woocommerce_buckaroo_creditcard_hosted_fields_client_secret' => 'client-secret-fixture',
            'woocommerce_other_gateway_api_secret' => 'other-secret-fixture',
        ];

        try {
            $page = new GeneralSettings($gateway);
            $current_section = '';
            $gateway->set_post_data($_POST);
            $page->save();
        } finally {
            $current_section = $previousSection;
        }
        // Positive control: debug logging must be active during this test
        Logger::log('test|control|', 'logging-active-marker');

        $log = $this->get_logged_text();
        $this->assertStringContainsString('logging-active-marker', $log);
        foreach (['secret-key-fixture', 'website-key-fixture', 'client-secret-fixture', 'other-secret-fixture'] as $fixture) {
            $this->assertStringNotContainsString($fixture, $log);
        }
    }

    public function test_settings_page_constructor_does_not_log_request_data(): void
    {
        // Settings pages are built before WooCommerce checks the nonce, so this is reachable unauthenticated
        $_POST = ['save' => 'Save changes', 'unauthenticated' => 'posted-value-fixture'];

        new GeneralSettings(new PaymentMethodSettings());
        // Positive control: debug logging must be active during this test
        Logger::log('test|control|', 'logging-active-marker');

        $this->assertSame(1, $this->count_log_rows());
        $this->assertStringContainsString('logging-active-marker', $this->get_logged_text());
    }

    public function test_request_context_omits_query_headers_cookies_and_environment(): void
    {
        $originalServer = $_SERVER;
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/checkout/?brq_service_ideal_consumeriban=NL00IBAN-FIXTURE',
            'QUERY_STRING' => 'brq_service_ideal_consumeriban=NL00IBAN-FIXTURE',
            'HTTP_REFERER' => 'https://shop.test/order-received/1/?key=wc_order_fixture',
            'HTTP_AUTHORIZATION' => 'hmac header-fixture',
            'HTTP_COOKIE' => 'wordpress_logged_in_x=cookie-fixture',
            'DB_PASS' => 'db-pass-fixture',
            'DATABASE_URL' => 'mysql://user:db-url-fixture@host/db',
        ];

        try {
            Logger::log('test|request|', LoggerStorage::request_context());
        } finally {
            $_SERVER = $originalServer;
        }

        $log = $this->get_logged_text();
        $this->assertStringContainsString("'REQUEST_PATH' => '/checkout/'", $log);
        $this->assertStringContainsString("'REQUEST_METHOD' => 'GET'", $log);
        foreach (['IBAN-FIXTURE', 'wc_order_fixture', 'header-fixture', 'cookie-fixture', 'db-pass-fixture', 'db-url-fixture'] as $fixture) {
            $this->assertStringNotContainsString($fixture, $log);
        }
    }

    public function test_logger_redacts_server_credentials(): void
    {
        Logger::log('test|server|', [
            'HTTP_AUTHORIZATION' => 'hmac header-fixture',
            'HTTP_COOKIE' => 'wordpress_logged_in_x=cookie-fixture',
            'PHP_AUTH_PW' => 'auth-pw-fixture',
            'REQUEST_URI' => '/?wc-api=WC_Push_Buckaroo',
        ]);

        $log = $this->get_logged_text();
        $this->assertStringNotContainsString('header-fixture', $log);
        $this->assertStringNotContainsString('cookie-fixture', $log);
        $this->assertStringNotContainsString('auth-pw-fixture', $log);
        $this->assertStringContainsString('WC_Push_Buckaroo', $log);
    }

    public function test_logger_redacts_response_parser_items(): void
    {
        Logger::log('test|parser|', ResponseParser::make([
            'brq_service_sepadirectdebit_customeriban' => 'NL00IBAN-FIXTURE',
            'brq_transactions' => 'TRX-REFERENCE',
        ]));

        $log = $this->get_logged_text();
        $this->assertStringNotContainsString('IBAN-FIXTURE', $log);
        $this->assertStringContainsString('TRX-REFERENCE', $log);
    }

    public function test_redact_shows_response_parser_values_changed_after_parsing(): void
    {
        $parser = ResponseParser::make(['brq_statuscode' => '791']);
        $parser->set('coreStatus', 'cancelled-override');

        $redacted = LoggerStorage::redact($parser);

        $this->assertSame('cancelled-override', $redacted['items']['corestatus']);
        $this->assertSame('791', $redacted['unformattedItems']['brq_statuscode']);
    }

    public function test_logger_redacts_credentials_in_context(): void
    {
        Logger::log('test|redaction|', [
            'secret_key' => 'secret-key-fixture',
            'client_secret' => 'client-secret-fixture',
            'nested' => ['websiteKey' => 'website-key-fixture'],
            'transaction_key' => 'TRX-REFERENCE',
        ]);

        $log = $this->get_logged_text();
        $this->assertSame(1, $this->count_log_rows());
        $this->assertStringNotContainsString('secret-key-fixture', $log);
        $this->assertStringNotContainsString('client-secret-fixture', $log);
        $this->assertStringNotContainsString('website-key-fixture', $log);
        $this->assertStringContainsString(LoggerStorage::REDACTED, $log);
        $this->assertStringContainsString('TRX-REFERENCE', $log);
    }

    /** @dataProvider sensitiveKeys */
    public function test_redact_replaces_sensitive_keys(string $key): void
    {
        $this->assertSame([$key => LoggerStorage::REDACTED], LoggerStorage::redact([$key => 'value']));
    }

    public function sensitiveKeys(): array
    {
        return [
            ['secretkey'],
            ['secret_key'],
            ['woocommerce_buckaroo_mastersettings_secretkey'],
            ['woocommerce_buckaroo_mastersettings_merchantkey'],
            ['hosted_fields_client_secret'],
            ['clientSecret'],
            ['store_key'],
            ['api_key'],
            ['password'],
            ['access_token'],
            ['_wpnonce'],
            ['buckaroo-sepadirectdebit-iban'],
            ['encrypted_card_data'],
            ['cardNumber'],
            ['cvc'],
            ['HTTP_AUTHORIZATION'],
            ['HTTP_COOKIE'],
            ['PHP_AUTH_PW'],
            ['order_key'],
        ];
    }

    /** @dataProvider referenceKeys */
    public function test_redact_keeps_reference_keys(string $key): void
    {
        $this->assertSame([$key => 'value'], LoggerStorage::redact([$key => 'value']));
    }

    public function referenceKeys(): array
    {
        return [
            ['transaction_key'],
            ['originalTransactionKey'],
            ['dataRequestKey'],
            ['PaymentKey'],
            ['Key'],
            ['RelatedTransactionKey'],
            ['amount'],
            ['buckaroo_creditcard_issuer'],
        ];
    }

    public function test_redact_handles_nested_objects_and_scalars(): void
    {
        $object = (object) ['secret' => 's', 'list' => [['token' => 't', 'id' => 1]]];

        $redacted = LoggerStorage::redact($object);

        $this->assertSame(LoggerStorage::REDACTED, $redacted->secret);
        $this->assertSame([['token' => LoggerStorage::REDACTED, 'id' => 1]], $redacted->list);
        $this->assertSame('s', $object->secret);
        $this->assertSame('plain message', LoggerStorage::redact('plain message'));
        $this->assertNull(LoggerStorage::redact(null));
    }

    public function test_redact_handles_name_value_parameters(): void
    {
        $parameters = [
            ['Name' => 'CustomerIBAN', 'GroupType' => '', 'Value' => 'NL00IBAN'],
            ['Name' => 'CustomerAccountName', 'Value' => 'J. Doe'],
        ];

        $this->assertSame(
            [
                ['Name' => 'CustomerIBAN', 'GroupType' => '', 'Value' => LoggerStorage::REDACTED],
                ['Name' => 'CustomerAccountName', 'Value' => 'J. Doe'],
            ],
            LoggerStorage::redact($parameters)
        );
    }

    public function test_redact_logs_throwables_without_call_arguments(): void
    {
        $exception = (function (string $secret) {
            return new RuntimeException('failure-message');
        })('arg-secret');

        $redacted = LoggerStorage::redact($exception);

        $this->assertIsString($redacted);
        $this->assertStringContainsString('RuntimeException: failure-message', $redacted);
        $this->assertStringContainsString('{closure', $redacted);
        $this->assertStringNotContainsString('arg-secret', $redacted);
    }

    public function test_redact_keeps_previous_throwables(): void
    {
        $exception = new RuntimeException('outer-message', 0, new LogicException('root-cause-message'));

        $redacted = LoggerStorage::redact($exception);

        $this->assertStringContainsString('RuntimeException: outer-message', $redacted);
        $this->assertStringContainsString('Caused by: LogicException: root-cause-message', $redacted);
    }

    public function test_redact_replaces_objects_past_max_depth_with_marker(): void
    {
        $nested = (object) ['leaf' => 'value'];
        for ($i = 0; $i < 40; $i++) {
            $nested = (object) ['child' => $nested];
        }

        $node = LoggerStorage::redact($nested);
        while ($node instanceof stdClass) {
            $node = $node->child;
        }

        $this->assertSame(LoggerStorage::REDACTED, $node);
    }

    private function get_logged_text(): string
    {
        global $wpdb;
        $table = $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE;

        return implode("\n", $wpdb->get_col("SELECT CONCAT(`location_id`, ' ', `message`) FROM $table"));
    }

    private function count_log_rows(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
}
