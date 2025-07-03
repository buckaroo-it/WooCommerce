<?php

namespace Buckaroo\Woocommerce\Core;

use Buckaroo\Woocommerce\Hooks\HookRegistry;

/**
 * Class Plugin
 *
 * Main class responsible for initializing and registering plugin components.
 */
class Plugin
{
    /**
     * Plugin version.
     *
     * @var string
     */
    public const VERSION = '4.3.1';

    /**
     * Instance of PaymentGatewayRegistry.
     */
    protected PaymentGatewayRegistry $gatewayRegistry;

    /**
     * Plugin constructor.
     *
     * Initializes the PaymentGatewayRegistry.
     */
    public function __construct()
    {
        $this->gatewayRegistry = new PaymentGatewayRegistry();
    }

    public function init(): void
    {
        add_action('woocommerce_init', [$this, 'registerGateways']);
        new HookRegistry();
    }

    /**
     * Register payment gateways with WooCommerce.
     */
    public function registerGateways(): void
    {
        $this->gatewayRegistry->load();

        add_filter(
            'woocommerce_payment_gateways',
            [$this->gatewayRegistry, 'hookGatewaysToWooCommerce']
        );
    }

    /**
     * Get the PaymentGatewayRegistry instance.
     */
    public function getGatewayRegistry(): PaymentGatewayRegistry
    {
        return $this->gatewayRegistry;
    }
}
