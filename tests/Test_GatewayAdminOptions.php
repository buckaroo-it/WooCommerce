<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use PHPUnit\Framework\TestCase;

/**
 * A gateway's settings page: the header returns to the Buckaroo menu and the
 * settings form follows without the method description.
 */
class Test_GatewayAdminOptions extends TestCase
{
    public function test_settings_page_links_back_to_buckaroo_and_renders_the_form(): void
    {
        if (! function_exists('wc_back_header')) {
            $this->markTestSkipped('This WooCommerce version has no wc_back_header()');
        }

        $gateway = new CreditCardGateway();
        $gateway->method_description = 'Method description fixture';

        ob_start();
        $gateway->admin_options();
        $html = ob_get_clean();

        $this->assertSame(1, substr_count($html, '<h2 class="wc-admin-header">'));
        $this->assertStringContainsString('aria-label="Back to Buckaroo settings"', $html);
        $this->assertStringContainsString('page=wc-settings&#038;tab=buckaroo_settings', $html);
        $this->assertStringNotContainsString('Return to payments', $html);

        $this->assertStringContainsString('<table class="form-table">', $html);
        $this->assertStringContainsString('name="' . $gateway->get_field_key('enabled') . '"', $html);

        $this->assertStringNotContainsString('Method description fixture', $html);
    }
}
