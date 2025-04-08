<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Gateways\In3\In3Gateway;
use Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankProcessor;

class PaymentSetupScripts {

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'handlePluginsLoaded' ), 0 );
		add_action( 'admin_enqueue_scripts', array( $this, 'handleAdminAssets' ) );
		add_action( 'enqueue_block_assets', array( $this, 'handleBlockAssets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'initFrontendScripts' ) );
	}

	public function handlePluginsLoaded() {
		load_plugin_textdomain(
			'wc-buckaroo-bpe-gateway',
			false,
			dirname( plugin_basename( BK_PLUGIN_FILE ) ) . '/languages/'
		);

		$transientKey = get_current_user_id() . '_buckaroo_require_woocommerce';
		if ( ! class_exists( 'WC_Order' ) ) {
			set_transient( $transientKey, true, HOUR_IN_SECONDS );

			return;
		}

		delete_transient( $transientKey );
	}

	public function handleAdminAssets(): void {
		$pluginDir = plugin_dir_url( BK_PLUGIN_FILE );

		wp_enqueue_style(
			'buckaroo-custom-styles',
			$pluginDir . 'library/css/buckaroo-custom.css',
			array(),
			Plugin::VERSION
		);
		wp_enqueue_script(
			'creditcard_capture',
			$pluginDir . 'library/js/creditcard-capture-form.js',
			array( 'jquery' ),
			Plugin::VERSION,
			true
		);
		wp_enqueue_script(
			'buckaroo_admin_utils_js',
			$pluginDir . 'library/js/util.js',
			array( 'jquery' ),
			Plugin::VERSION,
			true
		);
		if ( class_exists( 'WooCommerce' ) ) {
			wp_localize_script(
				'buckaroo_admin_utils_js',
				'buckaroo_php_vars',
				array(
					'version2' => In3Gateway::VERSION2,
					'in3_v2'   => In3Gateway::IN3_V2_TITLE,
					'in3_v3'   => In3Gateway::IN3_V3_TITLE,
				)
			);
		}
		wp_enqueue_script(
			'buckaroo-block-script',
			$pluginDir . 'assets/js/dist/blocks.js',
			array( 'wp-blocks', 'wp-element' )
		);
	}

	public function handleBlockAssets() {
        if ( has_block( 'woocommerce/checkout', get_post() ) ) {
            wp_enqueue_script(
                'buckaroo-blocks',
                plugins_url( '/assets/js/dist/blocks.js', BK_PLUGIN_FILE ),
                array( 'wc-blocks-registry', 'wp-element', 'wp-i18n', 'wp-data' ),
                Plugin::VERSION,
                true
            );

            wp_set_script_translations( 'buckaroo-blocks', 'wc-buckaroo-bpe-gateway', plugin_dir_path( BK_PLUGIN_FILE ) . 'languages' );
        }
	}

	public function initFrontendScripts() {
		if ( class_exists( 'WC_Order' ) && ( is_product() || is_checkout() || is_cart() ) ) {
			wp_enqueue_style(
				'buckaroo-custom-styles',
				plugin_dir_url( BK_PLUGIN_FILE ) . 'library/css/buckaroo-custom.css',
				array(),
				Plugin::VERSION
			);

			wp_enqueue_script(
				'buckaroo_sdk',
				'https://checkout.buckaroo.nl/api/buckaroosdk/script',
				// 'https://testcheckout.buckaroo.nl/api/buckaroosdk/script',
				array( 'jquery' ),
				Plugin::VERSION
			);

			wp_enqueue_script(
				'buckaroo_apple_pay',
				plugin_dir_url( BK_PLUGIN_FILE ) . 'assets/js/dist/applepay.js',
				array( 'jquery', 'buckaroo_sdk' ),
				Plugin::VERSION,
				true
			);

			wp_localize_script(
				'buckaroo_sdk',
				'buckaroo_global',
				array(
					'ajax_url'          => home_url( '/' ),
					'idin_i18n'         => array(
						'general_error' => esc_html__( 'Something went wrong while processing your identification.' ),
						'bank_required' => esc_html__( 'You need to select your bank!' ),
					),
					'payByBankLogos'    => PayByBankProcessor::getIssuerLogoUrls(),
					'creditCardIssuers' => ( new CreditCardGateway() )->getCardsList(),
					'locale'            => get_locale(),
				)
			);
		}

		if ( class_exists( 'WC_Order' ) && is_checkout() ) {
			wp_enqueue_script(
				'wc-pf-checkout',
				plugin_dir_url( BK_PLUGIN_FILE ) . 'assets/js/dist/checkout.js',
				array( 'jquery' ),
				Plugin::VERSION,
				true
			);
		}
	}
}
