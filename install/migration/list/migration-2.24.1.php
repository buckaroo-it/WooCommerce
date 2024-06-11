<?php

/**
 * Migration for version 2.24.1
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
return new class() implements Buckaroo_Migration
{
	public function execute() {
		global $wpdb;

		$wpdb->hide_errors();

		$collate = $this->getCollate();

		$wpdb->query(
			"
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}woocommerce_buckaroo_transactions (
                wc_orderid bigint(20) NOT NULL,
                transaction varchar(200) NOT NULL,
                PRIMARY KEY  (wc_orderid)
            ) 
            $collate;"
		);

		if ( ! get_option( 'woocommerce_buckaroo_exodus' ) ) {
			add_option( 'woocommerce_buckaroo_exodus', 'a:1:{s:8:"covenant";b:1;}', '', 'yes' );
		} else {
			update_option( 'woocommerce_buckaroo_exodus', 'a:1:{s:8:"covenant";b:1;}', true );
		}
		$this->create_log_table();
		$this->update_extrachargeamount_with_percentage();
	}
	/**
	 * Create table for logs if not exists
	 *
	 * @return void
	 */
	protected function create_log_table() {
		global $wpdb;

		$wpdb->hide_errors();
		$table   = $wpdb->prefix . Buckaroo_Logger_Storage::STORAGE_DB_TABLE;
		$collate = $this->getCollate();

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS $table (
                `id` BIGINT NOT NULL AUTO_INCREMENT , 
                `date` DATETIME NOT NULL , 
                `process_id` VARCHAR(23) NOT NULL ,
                `message` TEXT NOT NULL , 
                `location_id` VARCHAR(255) NOT NULL , 
                PRIMARY KEY (`id`)
            )  $collate;"
		);
	}
	protected function update_extrachargeamount_with_percentage() {

		$gateways = $this->get_our_gateways();
		/** @var WC_Gateway_Buckaroo */
		foreach ( $gateways as $gateway ) {
			$extrachargeamount = str_replace( '%', '', $gateway->get_option( 'extrachargeamount', 0 ) );

			if ( $extrachargeamount !== 0 && 'percentage' === $gateway->get_option( 'extrachargetype' ) ) {
				$gateway->update_option(
					'extrachargeamount',
					$extrachargeamount . '%'
				);
			}
		}
	}
	/**
	 * Filter gateways to display only our gateways
	 *
	 * @return array
	 */
	private function get_our_gateways() {

		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$gateways = WC()->payment_gateways->payment_gateways();

		return array_filter(
			$gateways,
			function ( $gateway ) {
				return $gateway instanceof WC_Gateway_Buckaroo;
			}
		);
	}
	/**
	 * Create table for logs if not exists
	 *
	 * @return void
	 */
	protected function getCollate() {
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			if ( ! empty( $wpdb->charset ) ) {
				$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( ! empty( $wpdb->collate ) ) {
				$collate .= " COLLATE $wpdb->collate";
			}
		}
		return $collate;
	}
};
