<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayButtons;
use Buckaroo\Woocommerce\Gateways\ExpressPaymentManager;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayButtons;
use Buckaroo\Woocommerce\Gateways\Paypal\PaypalGateway;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressOrder;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressShipping;
use Buckaroo\Woocommerce\Hooks\InitGateways;

class Test_ExpressCredentialValidation extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_currency', 'EUR');
        ExpressPaymentManager::getInstance()->clearPayments();
    }

    public function test_enabling_apple_pay_without_a_guid_shows_an_admin_error(): void
    {
        $gateway = new ApplepayGateway();
        $gateway->set_post_data([
            $gateway->get_field_key('enabled') => '1',
            $gateway->get_field_key('mode') => 'test',
            $gateway->get_field_key('merchant_guid') => '   ',
        ]);

        $gateway->process_admin_options();

        $this->assertCount(1, $gateway->get_errors());
        $this->assertStringContainsString('GUID', $gateway->get_errors()[0]);
        ob_start();
        WC_Admin_Settings::show_messages();
        $this->assertStringContainsString($gateway->get_errors()[0], (string) ob_get_clean());
    }

    /** @dataProvider applePayCredentials */
    public function test_apple_pay_availability_and_header_follow_required_credentials(array $settings, bool $configured): void
    {
        update_option('woocommerce_buckaroo_applepay_settings', $settings + ['enabled' => 'yes']);
        $gateway = new ApplepayGateway();

        $this->assertSame($configured, $gateway->is_available());
        $this->assertSame($configured, $gateway->isVisibleInCheckout());
        $this->assertSame(! $configured, strpos($this->header($gateway), 'Not configured') !== false);
    }

    public function applePayCredentials(): array
    {
        return [
            'missing Test' => [['mode' => 'test'], false],
            'empty Live' => [['mode' => 'live', 'merchant_guid' => ''], false],
            'whitespace Test' => [['mode' => 'test', 'merchant_guid' => " \t "], false],
            'default zero Live' => [['mode' => 'live', 'merchant_guid' => '0'], false],
            'valid Test' => [['mode' => 'test', 'merchant_guid' => 'merchant-guid'], true],
            'valid Live' => [['mode' => 'live', 'merchant_guid' => 'merchant-guid'], true],
        ];
    }

    public function test_apple_pay_without_credentials_is_hidden_from_express_buttons_and_blocks(): void
    {
        update_option('woocommerce_buckaroo_applepay_settings', [
            'enabled' => 'yes', 'mode' => 'live', 'merchant_guid' => '',
            'button_product' => 'TRUE', 'button_cart' => 'TRUE', 'button_checkout' => 'TRUE',
        ]);
        $manager = ExpressPaymentManager::getInstance();
        $manager->clearPayments();
        (new ApplepayButtons())->loadActions();

        foreach (['product', 'cart', 'checkout'] as $location) {
            ob_start();
            $manager->renderExpressPaymentsContainer($location);
            $this->assertSame('', ob_get_clean());
        }
        $data = $this->blocksData(new ApplepayGateway());
        $this->assertFalse($data['available']);
        $this->assertFalse($data['showInCheckout']);
        $manager->clearPayments();
    }

    public function test_disabled_apple_pay_can_save_incomplete_settings_without_an_error(): void
    {
        $gateway = new ApplepayGateway();
        $gateway->set_post_data([$gateway->get_field_key('mode') => 'live']);
        $gateway->process_admin_options();

        $this->assertSame([], $gateway->get_errors());
        $this->assertFalse((new ApplepayGateway())->is_available());
    }

    public function test_valid_apple_pay_settings_save_and_express_buttons_remain_available(): void
    {
        $gateway = new ApplepayGateway();
        $gateway->set_post_data([
            $gateway->get_field_key('enabled') => '1',
            $gateway->get_field_key('mode') => 'live',
            $gateway->get_field_key('merchant_guid') => 'merchant-guid',
            $gateway->get_field_key('button_cart') => 'TRUE',
            $gateway->get_field_key('button_checkout') => 'TRUE',
        ]);
        $gateway->process_admin_options();
        $this->assertSame([], $gateway->get_errors());
        $reloaded = new ApplepayGateway();
        $this->assertTrue($reloaded->is_available());
        $this->assertTrue($this->blocksData($reloaded)['showInCheckout']);

        $manager = ExpressPaymentManager::getInstance();
        $manager->clearPayments();
        (new ApplepayButtons())->loadActions();
        ob_start();
        $manager->renderExpressPaymentsContainer('cart');
        $this->assertStringContainsString('applepay-button-container', ob_get_clean());
        $manager->clearPayments();
    }

    public function test_unrelated_gateway_and_existing_disabled_restrictions_are_preserved(): void
    {
        update_option('woocommerce_buckaroo_ideal_settings', ['enabled' => 'yes', 'mode' => 'live']);
        $gateway = new IdealGateway();
        $this->assertTrue($gateway->is_available());
        $this->assertStringNotContainsString('Not configured', $this->header($gateway));

        update_option('woocommerce_buckaroo_applepay_settings', ['enabled' => 'no', 'merchant_guid' => 'merchant-guid']);
        $this->assertFalse((new ApplepayGateway())->is_available());
    }

    public function test_google_pay_requires_a_google_merchant_id_when_switching_from_test_to_live(): void
    {
        update_option('woocommerce_buckaroo_googlepay_settings', [
            'enabled' => 'yes', 'mode' => 'test', 'merchant_guid' => 'gateway-id', 'google_merchant_id' => '',
        ]);
        $gateway = new GooglepayGateway();
        $this->assertTrue($gateway->is_available());
        $gateway->set_post_data([
            $gateway->get_field_key('enabled') => '1',
            $gateway->get_field_key('mode') => 'live',
            $gateway->get_field_key('merchant_guid') => 'gateway-id',
            $gateway->get_field_key('google_merchant_id') => '',
        ]);
        $gateway->process_admin_options();

        $this->assertCount(1, $gateway->get_errors());
        $this->assertStringContainsString('Google Merchant ID', $gateway->get_errors()[0]);
        $this->assertFalse((new GooglepayGateway())->is_available());
    }

    /** @dataProvider googlePayCredentials */
    public function test_google_pay_checkout_and_express_buttons_follow_the_selected_mode(array $settings, bool $configured): void
    {
        update_option('woocommerce_buckaroo_googlepay_settings', $settings + [
            'enabled' => 'yes', 'button_cart' => 'TRUE', 'button_checkout' => 'TRUE',
        ]);
        $gateway = new GooglepayGateway();
        $this->assertSame($configured, $gateway->is_available());
        $this->assertSame($configured, $gateway->isVisibleInCheckout());
        $this->assertSame(! $configured, strpos($this->header($gateway), 'Not configured') !== false);
        $data = $this->blocksData($gateway);
        $this->assertSame($configured, $data['available']);
        $this->assertSame($configured, $data['showInCheckout']);

        $manager = ExpressPaymentManager::getInstance();
        (new GooglepayButtons())->loadActions();
        ob_start();
        $manager->renderExpressPaymentsContainer('cart');
        $this->assertSame($configured, strpos((string) ob_get_clean(), 'googlepay-button-container') !== false);
        $manager->clearPayments();
    }

    public function googlePayCredentials(): array
    {
        return [
            'missing gateway Test' => [['mode' => 'test', 'google_merchant_id' => 'google-id'], false],
            'zero gateway Live' => [['mode' => 'live', 'merchant_guid' => '0', 'google_merchant_id' => 'google-id'], false],
            'whitespace gateway Test' => [['mode' => 'test', 'merchant_guid' => '   '], false],
            'missing Google ID Test' => [['mode' => 'test', 'merchant_guid' => 'gateway-id'], true],
            'missing Google ID Live' => [['mode' => 'live', 'merchant_guid' => 'gateway-id'], false],
            'whitespace Google ID Live' => [['mode' => 'live', 'merchant_guid' => 'gateway-id', 'google_merchant_id' => '   '], false],
            'valid Live' => [['mode' => 'live', 'merchant_guid' => 'gateway-id', 'google_merchant_id' => 'google-id'], true],
            'empty mode' => [['mode' => '', 'merchant_guid' => 'gateway-id'], false],
            'unexpected mode' => [['mode' => 'unexpected', 'merchant_guid' => 'gateway-id'], false],
        ];
    }

    /** @dataProvider googlePayCredentials */
    public function test_google_pay_saves_report_errors_only_for_enabled_incomplete_settings(array $settings, bool $configured): void
    {
        $gateway = new GooglepayGateway();
        $post = [$gateway->get_field_key('enabled') => '1'];
        foreach ($settings as $key => $value) {
            $post[$gateway->get_field_key($key)] = $value;
        }
        $gateway->set_post_data($post);
        $gateway->process_admin_options();

        $this->assertSame(! $configured, count($gateway->get_errors()) > 0);
        $this->assertSame($configured, (new GooglepayGateway())->is_available());
    }

    public function test_google_pay_can_switch_to_test_or_disable_without_live_credentials(): void
    {
        update_option('woocommerce_buckaroo_googlepay_settings', [
            'enabled' => 'yes', 'mode' => 'live', 'merchant_guid' => 'gateway-id', 'google_merchant_id' => 'google-id',
        ]);
        $gateway = new GooglepayGateway();
        $gateway->set_post_data([
            $gateway->get_field_key('enabled') => '1',
            $gateway->get_field_key('mode') => 'test',
            $gateway->get_field_key('merchant_guid') => 'gateway-id',
            $gateway->get_field_key('google_merchant_id') => '',
        ]);
        $gateway->process_admin_options();
        $this->assertSame([], $gateway->get_errors());
        $this->assertTrue((new GooglepayGateway())->is_available());

        $gateway->set_post_data([$gateway->get_field_key('mode') => 'live']);
        $gateway->process_admin_options();
        $this->assertSame([], $gateway->get_errors());
        $this->assertFalse((new GooglepayGateway())->is_available());
    }

    public function test_paypal_requires_a_merchant_id_when_switching_to_live(): void
    {
        update_option('woocommerce_buckaroo_paypal_settings', ['enabled' => 'yes', 'mode' => 'test']);
        $gateway = new PaypalGateway();
        $this->assertTrue($gateway->is_available());
        $gateway->set_post_data([
            $gateway->get_field_key('enabled') => '1',
            $gateway->get_field_key('mode') => 'live',
            $gateway->get_field_key('express_merchant_id') => '',
            $gateway->get_field_key('express_sandbox_merchant_id') => 'sandbox-id',
        ]);
        $gateway->process_admin_options();

        $this->assertCount(1, $gateway->get_errors());
        $this->assertStringContainsString('merchant id', $gateway->get_errors()[0]);
        $this->assertFalse((new PaypalGateway())->is_available());
    }

    /** @dataProvider paypalCredentials */
    public function test_paypal_checkout_and_express_buttons_follow_required_live_credentials(array $settings, bool $configured): void
    {
        update_option('woocommerce_buckaroo_paypal_settings', $settings + [
            'enabled' => 'yes', 'express' => ['product', 'cart', 'checkout'],
        ]);
        $gateway = new PaypalGateway();
        $this->assertSame($configured, $gateway->is_available());
        $this->assertSame($configured, $gateway->isVisibleInCheckout());
        $this->assertSame(! $configured, strpos($this->header($gateway), 'Not configured') !== false);
        $data = $this->blocksData($gateway);
        $this->assertSame($configured, $data['available']);
        $this->assertSame($configured, $data['showInCheckout']);

        $manager = ExpressPaymentManager::getInstance();
        new PaypalExpressController(new PaypalExpressShipping(), new PaypalExpressOrder());
        foreach (['product', 'cart', 'checkout'] as $location) {
            ob_start();
            $manager->renderExpressPaymentsContainer($location);
            $this->assertSame($configured, strpos((string) ob_get_clean(), 'buckaroo-paypal-express') !== false);
        }
        $manager->clearPayments();
    }

    public function paypalCredentials(): array
    {
        return [
            'missing Test' => [['mode' => 'test'], true],
            'empty Test' => [['mode' => 'test', 'express_merchant_id' => ''], true],
            'missing Live' => [['mode' => 'live'], false],
            'whitespace Live' => [['mode' => 'live', 'express_merchant_id' => '   '], false],
            'sandbox only Live' => [['mode' => 'live', 'express_sandbox_merchant_id' => 'sandbox-id'], false],
            'valid Live' => [['mode' => 'live', 'express_merchant_id' => 'merchant-id'], true],
            'empty mode' => [['mode' => ''], false],
            'unexpected mode' => [['mode' => 'unexpected'], false],
        ];
    }

    /** @dataProvider paypalCredentials */
    public function test_paypal_saves_accept_empty_merchant_ids_only_in_test(array $settings, bool $configured): void
    {
        $gateway = new PaypalGateway();
        $post = [$gateway->get_field_key('enabled') => '1'];
        foreach ($settings as $key => $value) {
            $post[$gateway->get_field_key($key)] = $value;
        }
        $gateway->set_post_data($post);
        $gateway->process_admin_options();
        $this->assertSame(! $configured, count($gateway->get_errors()) > 0);
        $this->assertSame($configured, (new PaypalGateway())->is_available());
    }

    public function test_disabled_paypal_can_save_without_live_credentials(): void
    {
        $gateway = new PaypalGateway();
        $gateway->set_post_data([$gateway->get_field_key('mode') => 'live']);
        $gateway->process_admin_options();
        $this->assertSame([], $gateway->get_errors());
        $this->assertFalse((new PaypalGateway())->is_available());
    }

    public function test_paypal_express_without_a_saved_mode_uses_the_default_test_environment(): void
    {
        update_option('woocommerce_buckaroo_paypal_settings', ['enabled' => 'yes', 'express' => ['cart']]);
        $controller = new PaypalExpressController(new PaypalExpressShipping(), new PaypalExpressOrder());
        add_filter('woocommerce_is_cart', '__return_true');
        try {
            $controller->enqueue_scripts();
            $script = wp_scripts()->get_data('buckaroo_paypal_express', 'data');
            $this->assertIsString($script);
            $this->assertSame(1, preg_match('/var buckaroo_paypal_express = (.+);/', $script, $matches));
            $data = json_decode($matches[1], true);
            $this->assertTrue((bool) $data['is_test']);
        } finally {
            remove_filter('woocommerce_is_cart', '__return_true');
        }
    }

    private function header($gateway): string
    {
        $get = $_GET;
        $screen = get_current_screen();
        set_current_screen('woocommerce_page_wc-settings');
        $_GET['section'] = $gateway->id;
        try {
            ob_start();
            $gateway->generate_buckaroo_notice_html('buckaroo_notice', []);
            return (string) ob_get_clean();
        } finally {
            $_GET = $get;
            $GLOBALS['current_screen'] = $screen;
        }
    }

    private function blocksData($gateway): array
    {
        $gateways = WC()->payment_gateways();
        $previous = $gateways->payment_gateways;
        $gateways->payment_gateways = [$gateway->id => $gateway];
        try {
            return (new InitGateways())->initGatewaysOnCheckout()[0];
        } finally {
            $gateways->payment_gateways = $previous;
        }
    }
}
