<?php
/**
 * Buckaroo setup
 *
 * @package Buckaroo
 * @since   3.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Buckaroo Class.
 *
 * @class Buckaroo
 */
final class Buckaroo {


	/**
	 * The single instance of the class.
	 *
	 * @var Buckaroo
	 */
	protected static $_instance = null;

	/**
	 * Buckaroo Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
	}

	/**
	 * Main Buckaroo Instance.
	 *
	 * Ensures only one instance of Buckaroo is loaded or can be loaded.
	 *
	 * @since 2.1
	 * @static
	 * @return Buckaroo - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {

		include_once BK_ABSPATH . 'includes/class-wc-ajax.php';

		if ( $this->is_request( 'admin' ) ) {
			include_once BK_ABSPATH . 'includes/admin/class-wc-admin.php';
		}
	}

	/**
	 * Define Buckaroo Constants.
	 */
	private function define_constants() {

		$this->define( 'BK_ABSPATH', dirname( BK_PLUGIN_FILE ) . '/' );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * What type of request is this?
	 *
	 * @param  string $type admin, ajax, cron or frontend.
	 * @return bool
	 */
	private function is_request( $type ) {

		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) && ! $this->is_rest_api_request();
		}
	}
}
