<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Admin\GeneralSettings;
use Buckaroo\Woocommerce\Admin\PaymentMethodSettings;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use PHPUnit\Framework\TestCase;

class Test_SettingsCredentials extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        wp_cache_flush();
    }

    /** @dataProvider credentialFields */
    public function test_blank_saves_preserve_credentials_and_replacements_are_saved(string $class, string $key): void
    {
        $gateway = new $class();
        $option = $gateway->get_option_key();
        $original = get_option($option, null);
        try {
            update_option($option, [$key => 'original-credential']);
            $this->assertSame('original-credential', (new $class())->get_option($key), 'Credential fixture must be readable before saving');
            foreach (['', '   ', null, 'replacement-credential', '0'] as $submitted) {
                $gateway->set_post_data([$gateway->get_field_key($key) => $submitted]);
                $gateway->process_admin_options();
                $reloaded = new $class();
                $expected = $submitted === 'replacement-credential' || $submitted === '0'
                    ? $submitted : 'original-credential';
                $this->assertSame($expected, $reloaded->get_option($key), $key . " submitted " . var_export($submitted, true));
            }
        } finally {
            wp_cache_flush();
            if ($original === null) {
                delete_option($option);
            } else {
                update_option($option, $original);
            }
        }
    }

    public function credentialFields(): array
    {
        return [
            [PaymentMethodSettings::class, 'merchantkey'],
            [PaymentMethodSettings::class, 'secretkey'],
            [CreditCardGateway::class, 'hosted_fields_client_id'],
            [CreditCardGateway::class, 'hosted_fields_client_secret'],
        ];
    }

    /** @dataProvider credentialFields */
    public function test_password_rendering_hides_stored_values_and_distinguishes_unconfigured_fields(string $class, string $key): void
    {
        $gateway = new $class();
        $gateway->settings[$key] = 'secret-"<&-fixture';
        $html = $gateway->generate_password_html($key, $gateway->form_fields[$key]);
        $this->assertStringNotContainsString('fixture', $html);
        $this->assertStringContainsString('value=""', $html);
        $this->assertStringContainsString('placeholder="******"', $html);
        $this->assertStringContainsString('Saved. Enter a new value only to replace it.', $html);
        $this->assertStringContainsString('data-1p-ignore="true"', $html);
        $this->assertSame('secret-"<&-fixture', $gateway->get_option($key));

        $gateway->settings[$key] = '';
        $html = $gateway->generate_password_html($key, $gateway->form_fields[$key]);
        $this->assertStringNotContainsString('placeholder="******"', $html);
        $this->assertStringNotContainsString('Saved.', $html);
        $this->assertStringContainsString('value=""', $html);
    }

    public function test_settings_render_empty_credentials_without_reveal_controls(): void
    {
        $gateway = new PaymentMethodSettings();
        $gateway->settings['merchantkey'] = 'stored-merchant-fixture';
        $gateway->settings['secretkey'] = 'stored-secret-fixture';
        $page = new GeneralSettings($gateway);

        ob_start();
        $page->render_api_credentials_card_inner();
        $html = ob_get_clean();

        $this->assertStringNotContainsString('stored-merchant-fixture', $html);
        $this->assertStringNotContainsString('stored-secret-fixture', $html);
        $this->assertStringNotContainsString('bk-key-btn', $html);
        $this->assertSame(2, substr_count($html, 'value=""'));
        $this->assertSame(2, substr_count($html, 'placeholder="******"'));
        $this->assertSame(2, substr_count($html, 'Saved. Enter a new value only to replace it.'));
        $this->assertStringNotContainsString('required="required"', $html);
        $this->assertSame('stored-secret-fixture', $gateway->get_option('secretkey'));
    }

    public function test_settings_buttons_escape_their_custom_attributes(): void
    {
        $gateway = new class () extends PaymentMethodSettings {
            public function init_form_fields()
            {
                parent::init_form_fields();
                $this->form_fields['test_credentials']['custom_attributes'] = ['data-x"><b' => 'a"><script>'];
            }
        };

        ob_start();
        (new GeneralSettings($gateway))->render_api_credentials_card_inner();
        $html = ob_get_clean();

        $this->assertStringContainsString('title="Auto-configure"', $html);
        $this->assertStringContainsString('data-x&quot;&gt;&lt;b="a&quot;&gt;&lt;script&gt;"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
