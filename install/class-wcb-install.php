<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Install Class
 *
 * @package Buckaroo
 */
class WC_Buckaroo_Install {

	const DATABASE_VERSION_KEY = 'BUCKAROO_BPE_VERSION';

	/**
	 * @access public
	 * @return boolean (true)
	 */
	public static function install() {
		if ( self::isInstalled() ) {
			return;
		}
		// fresh install
		self::set_db_version( '0.0.0.0' );
		( new Buckaroo_Migration_Handler() )->handle();
		return true;
	}

	public static function isInstalled() {
		return self::get_db_version() !== false;
	}
	public static function isUntrackedInstall() {
		return self::get_db_version() === false && get_option( 'woocommerce_buckaroo_exodus' ) !== false;
	}
	/**
	 * Get database version
	 *
	 * @return void
	 */
	public static function get_db_version() {
		return get_option( self::DATABASE_VERSION_KEY );
	}
	/**
	 * Set database version
	 *
	 * @param string $version
	 *
	 * @return void
	 */
	public static function set_db_version( $version ) {
		update_option( self::DATABASE_VERSION_KEY, $version );
	}
	/**
	 * Do a install if the plugin was installed prior to 2.24.1
	 *
	 * @return void
	 */
	public static function installUntrackedInstalation() {
		if ( self::isUntrackedInstall() ) {
			self::install();
		}
	}
}
