<?php

namespace Buckaroo\Woocommerce\Services;

/**
 * Singleton for storing logger data
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */
class LoggerStorage {

	const STORAGE_FILE = 'file';
	const STORAGE_DB   = 'database';
	const STORAGE_ALL  = 'all';

	const STORAGE_FILE_LOCATION = 'api/log/';
	const STORAGE_DB_TABLE      = 'buckaroo_log';

	public static $storageList = array(
		self::STORAGE_ALL,
		self::STORAGE_FILE,
		self::STORAGE_DB,
	);

	public static function getStorage() {
		return Helper::get( 'logstorage' ) ?? self::STORAGE_FILE;
	}

	/**
	 * \Buckaroo\Woocommerce\Services\LoggerStorage Singleton
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Unique id for current instance
	 *
	 * @var string
	 */
	private static $processId;

	/**
	 * Private construct
	 */
	private function __construct() {
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function downloadFile( $name ) {
		$file = self::get_file_storage_location() . $name;

		if ( file_exists( $file ) ) {
			header( 'Expires: 0' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Cache-Control: pre-check=0, post-check=0, max-age=0', false );
			header( 'Pragma: no-cache' );
			header( 'Content-type: text/plain' );
			header( "Content-Disposition:attachment; filename={$name}" );

			readfile( $file );
			exit();
		} else {
			echo '<p>No log file found</p>';
			exit();
		}
	}

	/**
	 * Log into into storage
	 *
	 * @param mixed       $message
	 * @param string      $locationId
	 * @param string|null $method
	 *
	 * @return void
	 */
	public function log( string $locationId, $message ) {
		if ( Helper::get( 'debugmode' ) != 'on' ) {
			return;
		}

		$message = $this->format_message( $message );
		$storage = static::getStorage();
		$method  = $this->get_method_name( $storage );

		$date = date( 'Y-m-d h:i:s' );

		if ( method_exists( $this, $method ) ) {
			$this->{$method}(
				array( $date, $this->getProcessId(), $locationId, $message )
			);
		}
	}

	/**
	 * Format message for storage
	 *
	 * @param mixed $message
	 *
	 * @return string
	 */
	protected function format_message( $message ) {
		if ( is_object( $message ) || is_array( $message ) ) {
			return var_export( $message, true );
		}
		return $message;
	}

	/**
	 * Get method to handle the storing
	 *
	 * @param string $storage
	 *
	 * @return string
	 */
	protected function get_method_name( $storage ) {
		if ( ! in_array( $storage, static::$storageList ) ) {
			$storage = self::STORAGE_FILE;
		}
		return 'store_in_' . $storage;
	}

	protected static function getProcessId() {
		if ( empty( self::$processId ) ) {
			self::$processId = uniqid( '', true );
		}
		return self::$processId;
	}

	/**
	 * Store in database
	 *
	 * @param array $info
	 *
	 * @return void
	 */
	public function store_in_database( array $info ) {
		global $wpdb;
		$table = $wpdb->prefix . self::STORAGE_DB_TABLE;

		list($date, $processId, $locationId, $message) = $info;

		$data = array(
			'date'        => $date,
			'process_id'  => $processId,
			'message'     => $message,
			'location_id' => $locationId,
		);

		$format = array( '%s', '%s', '%s' );
		$wpdb->insert(
			$table,
			$data,
			$format
		);
	}

	/**
	 * Store in all storage mediums
	 *
	 * @param array $info
	 *
	 * @return void
	 */
	protected function store_in_all( array $info ) {
		$storageList = array_diff( static::$storageList, array( 'all' ) );
		foreach ( $storageList as $storage ) {
			$method = $this->get_method_name( $storage );
			if ( method_exists( $this, $method ) ) {
				$this->{$method}( $info );
			}
		}
	}

	/**
	 * Store in file
	 *
	 * @param array $info
	 *
	 * @return void
	 */
	protected function store_in_file( array $info ) {
		@file_put_contents(
			self::get_file_storage_location() . date( 'd-m-Y' ) . '.log',
			implode(
				'|||',
				$info
			) . PHP_EOL,
			FILE_APPEND
		);
	}

	public static function get_file_storage_location() {
		return plugin_dir_path( BK_PLUGIN_FILE ) . self::STORAGE_FILE_LOCATION;
	}
}
