<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Admin\GeneralSettings;
use Buckaroo\Woocommerce\Admin\PaymentMethodSettings;

class AdminHooks {

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'handleNotices' ) );
		add_action( 'admin_menu', array( $this, 'addPagesMenu' ) );
		add_action( 'woocommerce_get_settings_pages', array( $this, 'handlePages' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( BK_PLUGIN_FILE ), array( $this, 'handleActionLinks' ) );
	}

	/**
	 * Add link to plugin settings in plugin list
	 * plugin_action_links_'.plugin_basename(__FILE__)
	 *
	 * @param array $actions
	 *
	 * @return array $actions
	 */
	public function handleActionLinks( $actions ) {
		$settingsLink = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=buckaroo_settings' ) . '">' . esc_html__( 'Settings', 'wc-buckaroo-bpe-gateway' ) . '</a>',
		);
		$actions      = array_merge( $actions, $settingsLink );

		return $actions;
	}


	/**
	 * Add the buckaroo tab to woocommerce settings page
	 *
	 * @param array $settings Array of woocommerce tabs
	 *
	 * @return array $settings Array of woocommerce tabs
	 */
	public function handlePages( $settings ) {
		$settings[] = new GeneralSettings( new PaymentMethodSettings() );

		return $settings;
	}

	public function addPagesMenu(): void {
		add_menu_page(
			'Buckaroo',
			'Buckaroo',
			'read',
			'admin.php?page=wc-settings&tab=buckaroo_settings',
			'',
			'data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjIiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDYwIDYwIiB3aWR0aD0iNjAiIGhlaWdodD0iNjAiPgoJPHRpdGxlPk5pZXV3IHByb2plY3Q8L3RpdGxlPgoJPGRlZnM+CgkJPGltYWdlICB3aWR0aD0iMzgiIGhlaWdodD0iMzYiIGlkPSJpbWcxIiBocmVmPSJkYXRhOmltYWdlL3BuZztiYXNlNjQsaVZCT1J3MEtHZ29BQUFBTlNVaEVVZ0FBQUNZQUFBQWtDQU1BQUFEU0s3aVhBQUFBQVhOU1IwSUIyY2tzZndBQUFHWlFURlJGdnVaaXpQVmp5UEZqQUFBQXdlbGl4ZTVqeXZOangvQmp4ZTFpdDk5Z3N0dGhxdDFteS9SanhPeGl3T2xpdk9SaXcrdGlyZFpqdXVKaHROeGh3T2hpdWVCaXh1NWp2K2RpeHU5anlmSmp4KzlqdStGand1cGl2ZVZpelBSanl2Smp1TjFndk9SaURzdVRJUUFBQUNKMFVrNVQvLy8vQVAvLy8vLy9uMDhQLy8vLzcvOGZ2MS8vci8vLy8vLy96Ly8vLy8rUDM4cmhmTllBQUFFeVNVUkJWSGljalpScFV3SXhESVpiRjVSckZBK0VFUjM5L3o4TFVNWVRoUUVFQk53bTZaRnN1NUFQVFpzKyt5YnR0dFZLSzJNYW5UUzlCVmZSMlE0REoxSE1UaDZOVWJlNmpWQ1ZOVW5rV0hXVmxOdlp1bk9NQnJWMWdUcGJnbXY4QmxoeldjQ3lqYzFqTU9KYUMwRTFaK0RxSzRhbHpCUU5HSWtmd2tybFlBY1FhMDJUMU1YTVl5Vnl1SjJFMWVkSFllZmY0QzUvM0hUN0N4eHRPbUdVOVhyaWE1b0VZZzY3K2dpanJveHN6ekdTNjN6UzhPYU5mU1l3RnhkRGg5RUV4OXlTUE5aNU5XMFBrM1hIcHZWSDFXTk1EdnY1UVV0aHQyYkp2UmRXQWNQd3g4THB4Q1BUSDhld0lDdGZqc0Qyc0pmNW43Z2JtYzdEY3h4eklnV3hHR1l0aVdFeXN1NTdDbFAzZzdpWXdHcitEajRPMDFoUUhYOHJCRVkzV0tuVHZ6TE15WW1IUjJMMjdoekFTTzVwd0tQL2RnUlNrMm1ydzZVQUFBQUFTVVZPUks1Q1lJST0iLz4KCTwvZGVmcz4KCTxzdHlsZT4KCTwvc3R5bGU+Cgk8dXNlIGlkPSJMYWFnIDIiIGhyZWY9IiNpbWcxIiB4PSIxMSIgeT0iMTIiLz4KPC9zdmc+',
			'55.3'
		);
		add_submenu_page(
			'admin.php?page=wc-settings&tab=buckaroo_settings',
			esc_html__( 'Settings', 'wc-buckaroo-bpe-gateway' ),
			esc_html__( 'Settings', 'wc-buckaroo-bpe-gateway' ),
			'manage_options',
			'admin.php?page=wc-settings&tab=buckaroo_settings'
		);
		add_submenu_page(
			'admin.php?page=wc-settings&tab=buckaroo_settings',
			esc_html__( 'Payment methods', 'wc-buckaroo-bpe-gateway' ),
			esc_html__( 'Payment methods', 'wc-buckaroo-bpe-gateway' ),
			'manage_options',
			'admin.php?page=wc-settings&tab=buckaroo_settings&section=methods'
		);
		add_submenu_page(
			'admin.php?page=wc-settings&tab=buckaroo_settings',
			esc_html__( 'Report', 'wc-buckaroo-bpe-gateway' ),
			esc_html__( 'Report', 'wc-buckaroo-bpe-gateway' ),
			'manage_options',
			'admin.php?page=wc-settings&tab=buckaroo_settings&section=report'
		);
	}


	public function handleNotices(): void {
		if ( $message = get_transient( get_current_user_id() . 'buckarooAdminNotice' ) ) {
			delete_transient( get_current_user_id() . 'buckarooAdminNotice' );
			echo '<div class="notice notice-' . esc_attr( $message['type'] ) . ' is-dismissible"><p>' . wp_kses(
				$message['message'],
				array(
					'b' => array(),
					'p' => array(),
				)
			) . '</p></div>';
		}
		if ( get_transient( get_current_user_id() . 'buckaroo_require_woocommerce' ) ) {
			delete_transient( get_current_user_id() . 'buckaroo_require_woocommerce' );
			echo '<div class="notice notice-error"><p>' . esc_html__(
				'Buckaroo BPE requires WooCommerce to be installed and active',
				'wc-buckaroo-bpe-gateway'
			) . '</p></div>';
		}
	}
}
