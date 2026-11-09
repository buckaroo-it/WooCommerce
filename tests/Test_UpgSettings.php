<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Admin\GeneralSettings;
use Buckaroo\Woocommerce\Admin\PaymentMethodSettings;
use Buckaroo\Woocommerce\Services\UpgSettings;
use BuckarooDeps\Buckaroo\Upg\Testing\FakeHttpClient;
use BuckarooDeps\Buckaroo\Upg\Testing\Fixtures;
use BuckarooDeps\Buckaroo\Upg\Testing\RecordedRequest;
use PHPUnit\Framework\TestCase;

class Test_UpgSettings extends TestCase
{
    use UpgTestSupport;

    private const MASTER_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    protected function setUp(): void
    {
        parent::setUp();
        wp_cache_flush();
        $this->saveOptions(self::MASTER_OPTION, UpgSettings::STATE_OPTION);
        delete_option(UpgSettings::STATE_OPTION);
    }

    protected function tearDown(): void
    {
        $this->restoreOptions();
        parent::tearDown();
    }

    public function test_existing_merchants_keep_bpe(): void
    {
        update_option(self::MASTER_OPTION, ['merchantkey' => 'store-key', 'secretkey' => 'secret-key']);
        update_option(UpgSettings::STATE_OPTION, ['test' => ['methods' => ['wero']]]);

        $this->assertFalse(UpgSettings::isChosen());
        $this->assertSame([], UpgSettings::methods('test'));
        $this->assertSame('bpe', (new PaymentMethodSettings())->form_fields['api']['default']);
    }

    public function test_a_wrong_key_shows_an_error_and_stores_no_methods(): void
    {
        $this->chooseUpg(['upg_test_key' => 'apk_wrong']);
        update_option(UpgSettings::STATE_OPTION, ['test' => ['methods' => ['wero'], 'webhook_secret' => str_repeat('s', 40)]]);
        $this->fakeUpg(['*' => FakeHttpClient::response(Fixtures::problem(401, 'Unauthorized', 'The API key is not valid.'), 401)]);

        $errors = UpgSettings::sync();

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('test', $errors[0]);
        $this->assertSame([], UpgSettings::methods('test'));
    }

    public function test_a_valid_key_stores_the_methods_and_the_webhook_secret_and_a_new_save_reuses_the_webhook(): void
    {
        $this->chooseUpg(['upg_test_key' => 'apk_test']);
        $this->fakeUpg([
            'POST /v1/paymentmethods/subscriptions/search' => ['subscriptions' => [['serviceCode' => 'Wero', 'status' => 'Enabled'], ['serviceCode' => 'WeroQr', 'status' => 'Enabled']]],
            'POST /v1/webhooks/search' => FakeHttpClient::sequence()
                ->push(['webhooks' => []])
                ->push(['webhooks' => [Fixtures::webhookEndpoint(['url' => UpgSettings::webhookUrl(), 'eventTypes' => ['SALE.UPDATE'], 'status' => 'Active'])]]),
            'POST /v1/webhooks' => FakeHttpClient::response(Fixtures::webhookEndpoint(), 201),
        ]);

        $this->assertSame([], UpgSettings::sync());
        $secrets = UpgSettings::webhookSecrets();
        $this->assertSame(['wero'], UpgSettings::methods('test'));
        $this->assertCount(1, $secrets);
        $this->assertSame(UpgSettings::webhookUrl(), $this->http->recorded()[2]->data()['url']);

        $this->assertSame([], UpgSettings::sync());
        $this->assertSame($secrets, UpgSettings::webhookSecrets());
        $creates = $this->http->recorded(fn (RecordedRequest $request): bool => $request->method() === 'POST' && $request->path() === '/v1/webhooks');
        $this->assertCount(1, $creates);
    }

    public function test_a_temporary_upg_error_keeps_the_stored_methods_and_does_not_blame_the_key(): void
    {
        $this->chooseUpg(['upg_test_key' => 'apk_test']);
        update_option(UpgSettings::STATE_OPTION, ['test' => ['methods' => ['wero'], 'webhook_secret' => str_repeat('s', 40)]]);
        $this->fakeUpg(['POST /v1/paymentmethods/subscriptions/search' => FakeHttpClient::response(Fixtures::problem(500, 'Server Error'), 500)]);

        $errors = UpgSettings::sync();

        $this->assertCount(1, $errors);
        $this->assertStringNotContainsString('does not work', $errors[0]);
        $this->assertSame(['wero'], UpgSettings::methods('test'));
    }

    /** Only the webhook marks a UPG order paid, so without it the methods stay on BPE. */
    public function test_a_webhook_that_cannot_be_registered_keeps_payments_on_bpe_and_shows_why(): void
    {
        $this->chooseUpg(['upg_test_key' => 'apk_test']);
        $this->fakeUpg([
            'POST /v1/paymentmethods/subscriptions/search' => ['subscriptions' => [['serviceCode' => 'Wero', 'status' => 'Enabled']]],
            'POST /v1/webhooks/search' => ['webhooks' => []],
            'POST /v1/webhooks' => FakeHttpClient::response(Fixtures::problem(400, 'Bad Request', "URL points to the reserved domain 'shop.test'."), 400),
        ]);

        $errors = UpgSettings::sync();

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('webhook', $errors[0]);
        $this->assertSame([], UpgSettings::methods('test'));
        $this->assertSame([], UpgSettings::webhookSecrets());
    }

    public function test_a_mode_without_a_key_sends_nothing_to_upg(): void
    {
        $this->chooseUpg(['upg_live_key' => 'apk_live']);
        $this->fakeUpg(['POST /v1/paymentmethods/subscriptions/search' => ['subscriptions' => [['serviceCode' => 'Wero', 'status' => 'Enabled']]], 'POST /v1/webhooks/search' => ['webhooks' => []], 'POST /v1/webhooks' => FakeHttpClient::response(Fixtures::webhookEndpoint(), 201)]);

        UpgSettings::sync();

        $this->assertSame([], UpgSettings::methods('test'));
        $this->assertNull(UpgSettings::client('test'));
        $this->assertSame(['wero'], UpgSettings::methods('live'));
    }

    public function test_saving_the_general_settings_with_upg_chosen_runs_the_upg_setup(): void
    {
        global $current_section;
        $current_section = '';
        update_option(self::MASTER_OPTION, ['merchantkey' => 'store-key', 'secretkey' => 'secret-key']);
        $this->fakeUpg([
            'POST /v1/paymentmethods/subscriptions/search' => ['subscriptions' => [['serviceCode' => 'Wero', 'status' => 'Enabled']]],
            'POST /v1/webhooks/search' => ['webhooks' => []],
            'POST /v1/webhooks' => FakeHttpClient::response(Fixtures::webhookEndpoint(), 201),
        ]);
        $gateway = new PaymentMethodSettings();
        $gateway->set_post_data([
            $gateway->get_field_key('api') => 'upg',
            $gateway->get_field_key('upg_test_key') => 'apk_test',
        ]);

        (new GeneralSettings($gateway))->save();

        wp_cache_flush();
        $this->assertTrue(UpgSettings::isChosen());
        $this->assertSame(['wero'], UpgSettings::methods('test'));
    }

    private function chooseUpg(array $keys): void
    {
        update_option(self::MASTER_OPTION, ['merchantkey' => 'store-key', 'secretkey' => 'secret-key', 'api' => 'upg'] + $keys);
    }
}
