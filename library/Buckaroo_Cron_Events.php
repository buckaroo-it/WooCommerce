<?php

require_once 'config.php';
/**
 * Core class for logging
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

class Buckaroo_Cron_Events {

	/**
	 * Number of days to keep the logs
	 */
	const LOG_STALE_DAYS = 14;

	public function __construct() {

		add_action(
			'buckaroo_clean_logger_storage',
			array( $this, 'clean_logger_storage' )
		);

		if ( ! wp_next_scheduled( 'buckaroo_clean_logger_storage' ) ) {
			wp_schedule_event(
				time(),
				'daily',
				'buckaroo_clean_logger_storage'
			);
		}
	}

	/**
	 * Hook function for clearing stale logs
	 *
	 * @return void
	 */
	public function clean_logger_storage() {
		$storage = BuckarooConfig::get( 'logstorage' ) ?? Buckaroo_Logger_Storage::STORAGE_ALL;
		$method  = $this->get_logger_clean_method_name( $storage );
		if ( method_exists( $this, $method ) ) {
			$this->{$method}();
		}
	}
	/**
	 * Unschedule the events
	 *
	 * @return void
	 */
	public static function unschedule() {
			$timestamp = wp_next_scheduled( 'buckaroo_clean_logger_storage' );
			wp_unschedule_event( $timestamp, 'buckaroo_clean_logger_storage' );
	}
	protected function get_logger_clean_method_name( $storage ) {
		if ( strlen( $storage ) === 0 ) {
			$storage = Buckaroo_Logger_Storage::STORAGE_ALL;
		}

		return 'clean_logger_storage_' . $storage;
	}
	/**
	 * Clean all logger storage mediums
	 *
	 * @return void
	 */
	protected function clean_logger_storage_all() {
		$storageList = array_diff( Buckaroo_Logger_Storage::$storageList, array( 'all' ) );
		foreach ( $storageList as $storage ) {
			$method = $this->get_logger_clean_method_name( $storage );
			if ( method_exists( $this, $method ) ) {
				$this->{$method}();
			}
		}
	}
	/**
	 * Clean file storage
	 *
	 * @return void
	 */
	protected function clean_logger_storage_file() {
		$directory = Buckaroo_Logger_Storage::get_file_storage_location();
		$staleDate = $this->get_stale_date();
		$logs      = glob( $directory . '*.log' );
		foreach ( $logs as $fileName ) {
			try {
				$date = DateTime::createFromFormat(
					'd-m-Y',
					str_replace( '.log', '', basename( $fileName ) )
				);

				if (
					$date < $staleDate &&
					file_exists( $fileName )
				) {
					unlink( $fileName );
				}
			} catch ( \Throwable $th ) {
				Buckaroo_Logger::log( __METHOD__, 'Invalid file name for log: ' . $fileName );
			}
		}
	}
	/**
	 * Check if line is older than required
	 *
	 * @param \DateTime|null $date
	 *
	 * @return boolean
	 */
	protected function is_file_line_not_stale( $date ) {
		if ( $date === null ) {
			return true;
		}

		$staleDate = $this->get_stale_date();
		return $date > $staleDate;
	}
	/**
	 * Get stale date
	 *
	 * @return \DateTime
	 */
	protected function get_stale_date() {
		return ( new \DateTime() )
		->sub( new \DateInterval( 'P' . self::LOG_STALE_DAYS . 'D' ) );
	}
	/**
	 * Clean database storage
	 *
	 * @return void
	 */
	protected function clean_logger_storage_database() {
		global $wpdb;
		$wpdb->hide_errors();

		$table     = $wpdb->prefix . Buckaroo_Logger_Storage::STORAGE_DB_TABLE;
		$staleDate = $this->get_stale_date()->format( 'Y-m-d H:i:s' );
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE `date` < %s", $staleDate )
		);
	}
}
