<?php
/**
 * WooCommerce Admin
 *
 * @class    BK_Admin
 * @package  WooCommerce/Admin
 * @version  2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * BK_Admin class.
 */
class BK_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'includes' ) );
	}

	/**
	 * Output buffering allows admin screens to make redirects later on.
	 */
	public function buffer() {
		ob_start();
	}

	/**
	 * Include any classes we need within admin.
	 */
	public function includes() {
		include_once __DIR__ . '/class-wc-admin-post-types.php';
	}
}

return new BK_Admin();
