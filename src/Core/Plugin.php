<?php

namespace Buckaroo\Woocommerce\Core;

use Buckaroo\Woocommerce\Hooks\HookRegistry;

/**
 * Class Plugin
 *
 * Main class responsible for initializing and registering plugin components.
 */
class Plugin {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const  VERSION = '4.0.0';

	/**
	 * Instance of PaymentGatewayRegistry.
	 *
	 * @var PaymentGatewayRegistry
	 */
	protected PaymentGatewayRegistry $gatewayRegistry;

	/**
	 * Plugin constructor.
	 *
	 * Initializes the PaymentGatewayRegistry.
	 */
	public function __construct() {
		$this->gatewayRegistry = new PaymentGatewayRegistry();
	}

	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'registerGateways' ), 0 );
		new HookRegistry();
	}

	/**
	 * Register payment gateways with WooCommerce.
	 *
	 * @return void
	 */
	public function registerGateways(): void {
		$this->gatewayRegistry->load();

		add_filter(
			'woocommerce_payment_gateways',
			array( $this->gatewayRegistry, 'hookGatewaysToWooCommerce' )
		);
	}


	/**
	 * Get the PaymentGatewayRegistry instance.
	 *
	 * @return PaymentGatewayRegistry
	 */
	public function getGatewayRegistry(): PaymentGatewayRegistry {
		return $this->gatewayRegistry;
	}
}
