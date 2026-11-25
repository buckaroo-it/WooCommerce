<?php

declare(strict_types=1);

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Buckaroo\Woocommerce\Hooks\InitGateways;

/**
 * Checkout block integrations of the Buckaroo gateways. Every gateway is
 * registered, so the WooCommerce Payments settings page does not show
 * "Limited compatibility" for a Buckaroo method enabled there, and only the
 * enabled gateways are active at checkout.
 */
class Test_BlocksRegistration extends WP_UnitTestCase
{
    private const IDEAL_OPTION = 'woocommerce_buckaroo_ideal_settings';

    private const SWISH_OPTION = 'woocommerce_buckaroo_swish_settings';

    protected function setUp(): void
    {
        parent::setUp();

        update_option(self::IDEAL_OPTION, ['enabled' => 'yes']);
        update_option(self::SWISH_OPTION, ['enabled' => 'no']);
        WC()->payment_gateways()->init();
    }

    protected function tearDown(): void
    {
        delete_option(self::IDEAL_OPTION);
        delete_option(self::SWISH_OPTION);
        WC()->payment_gateways()->init();

        parent::tearDown();
    }

    public function test_every_buckaroo_gateway_has_a_registered_integration(): void
    {
        $registry = $this->registerIntegrations();
        $gatewayIds = $this->buckarooGatewayIds();

        $this->assertContains('buckaroo_swish', $gatewayIds);
        foreach ($gatewayIds as $gatewayId) {
            $this->assertTrue($registry->is_registered($gatewayId), $gatewayId);
        }
    }

    public function test_only_enabled_gateways_are_active(): void
    {
        $active = array_keys($this->registerIntegrations()->get_all_active_registered());

        $this->assertContains('buckaroo_ideal', $active);
        $this->assertNotContains('buckaroo_swish', $active);
    }

    private function registerIntegrations(): PaymentMethodRegistry
    {
        $registry = new PaymentMethodRegistry();
        (new InitGateways())->registerBuckarooExpressBlocks($registry);

        return $registry;
    }

    /**
     * @return string[]
     */
    private function buckarooGatewayIds(): array
    {
        return array_values(array_filter(
            array_keys(WC()->payment_gateways()->payment_gateways()),
            static fn ($gatewayId) => strpos((string) $gatewayId, 'buckaroo_') === 0
        ));
    }
}
