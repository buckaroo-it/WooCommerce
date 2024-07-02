<?php

/**
 * @package Buckaroo
 */
class BuckarooIdin {

	/**
	 * @access public
	 * @return array $issuerArray
	 */
	public static function getIssuerList() {
		$issuers = array(
			array(
				'servicename' => 'ABNANL2A',
				'displayname' => 'ABN AMRO',
			),
			array(
				'servicename' => 'ASNBNL21',
				'displayname' => 'ASN Bank',
			),
			array(
				'servicename' => 'BUNQNL2A',
				'displayname' => 'bunq',
			),
			array(
				'servicename' => 'INGBNL2A',
				'displayname' => 'ING',
			),
			array(
				'servicename' => 'RABONL2U',
				'displayname' => 'Rabobank',
			),
			array(
				'servicename' => 'RBRBNL21',
				'displayname' => 'RegioBank',
			),
			array(
				'servicename' => 'SNSBNL2A',
				'displayname' => 'SNS Bank',
			),
			array(
				'servicename' => 'TRIONL2U',
				'displayname' => 'Triodos Bank',
			),
		);

		if ( BuckarooConfig::getIdinMode() == 'test' ) {
			$issuers[] = array(
				'servicename' => 'BANKNL2Y',
				'displayname' => 'TEST BANK',
			);
		}
		return $issuers;
	}

	public static function checkIfValidIssuer( $code ) {
		$issuerList = self::getIssuerList();
		foreach ( $issuerList as $issuer ) {
			if ( $issuer['servicename'] == $code ) {
				return true;
			}
		}
		return false;
	}

	public static function checkCurrentUserIsVerified() {
		if ( ! BuckarooConfig::isIdin( self::getCartProductIds() ) ) {
			return true;
		}

		if ( $currentUserId = get_current_user_id() ) {
			return get_user_meta( $currentUserId, 'buckaroo_idin', true );
		} else {
			return WC()->session->get( 'buckaroo_idin' );
		}
		return false;
	}

	public static function setCurrentUserIsVerified( $bin ) {
		$currentUserId = get_current_user_id();
		Buckaroo_Logger::log('get_current_user_id result:', $currentUserId);

		if ( $currentUserId ) {
			Buckaroo_Logger::log('Setting user meta for user ID:', $currentUserId);
			if (!add_user_meta( $currentUserId, 'buckaroo_idin', 1, true )) {
				Buckaroo_Logger::log('Failed to add user meta for buckaroo_idin');
			}
			if (!add_user_meta( $currentUserId, 'buckaroo_idin_bin', $bin, true )) {
				Buckaroo_Logger::log('Failed to add user meta for buckaroo_idin_bin');
			}
		} else {
			Buckaroo_Logger::log('Setting session data as no user ID found');
			WC()->session->set( 'buckaroo_idin', 1 );
			WC()->session->set( 'buckaroo_idin_bin', $bin );
		}
	}

	public static function setCurrentUserIsNotVerified() {
		$currentUserId = get_current_user_id();
		Buckaroo_Logger::log('get_current_user_id result:', $currentUserId);

		if ( $currentUserId ) {
			Buckaroo_Logger::log('Deleting user meta for user ID:', $currentUserId);
			if (!delete_user_meta( $currentUserId, 'buckaroo_idin' )) {
				Buckaroo_Logger::log('Failed to delete user meta for buckaroo_idin');
			}
			if (!delete_user_meta( $currentUserId, 'buckaroo_idin_bin' )) {
				Buckaroo_Logger::log('Failed to delete user meta for buckaroo_idin_bin');
			}
		} else {
			Buckaroo_Logger::log('Setting session data as no user ID found');
			WC()->session->set( 'buckaroo_idin', 0 );
			WC()->session->set( 'buckaroo_idin_bin', 0 );
		}
	}

	public static function getCartProductIds() {
		global $woocommerce;

		$productIds = array();

		if ( $woocommerce->cart ) {
			$items = $woocommerce->cart->get_cart();

			foreach ( $items as $item ) {
				$productIds[] = $item['data']->get_id();
			}
		}

		return $productIds;
	}
}
