<?php

require_once 'config.php';
/**
 * Core class to disable gateways
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
class Buckaroo_Disable_Gateways {

	public function __construct() {
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'disable' ) );
	}
	public function disable( $available_gateways ) {
		if ( ! BuckarooIdin::checkCurrentUserIsVerified() ) {
			return array();
		}

		if ( $available_gateways ) {
			if ( ! empty( WC()->cart ) ) {
				$totalCartAmount = WC()->cart->get_total( null );

				/**
				 * skip check when card total is 0
				 */
				if ( $totalCartAmount == 0 ) {
					return $available_gateways;
				}

				foreach ( $available_gateways as $key => $gateway ) {
					if (
						$this->isBuckarooPayment( $key ) &&
						method_exists( $gateway, 'isAvailable' ) &&
						! $gateway->isAvailable( $totalCartAmount )
					) {
						unset( $available_gateways[ $key ] );
					}
					if (
						$this->isBuckarooPayment( $key )
						&& (
							! empty( $gateway->minvalue )
							||
							! empty( $gateway->maxvalue )
						)
					) {
						if ( ! empty( $gateway->maxvalue ) && $totalCartAmount > $gateway->maxvalue ) {
							unset( $available_gateways[ $key ] );
						}

						if ( ! empty( $gateway->minvalue ) && $totalCartAmount < $gateway->minvalue ) {
							unset( $available_gateways[ $key ] );
						}
					}
				}
			}
		}

		if ( isset( $available_gateways['buckaroo_applepay'] ) ) {
			unset( $available_gateways['buckaroo_applepay'] );
		}
		if ( isset( $available_gateways['buckaroo_payperemail'] ) && $available_gateways['buckaroo_payperemail']->frontendVisible === 'no' ) {
			unset( $available_gateways['buckaroo_payperemail'] );
		}
		return $available_gateways;
	}
	/**
	 * Check if payment gateway is ours
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	protected function isBuckarooPayment( string $name ) {
		return substr( $name, 0, 8 ) === 'buckaroo';
	}
}
