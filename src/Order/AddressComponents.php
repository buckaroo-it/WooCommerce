<?php

namespace Buckaroo\Woocommerce\Order;

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
class AddressComponents {

	private $components;

	public function __construct( string $address ) {
		$this->components = $this->get_address_components( $address );
	}

	/**
	 * Split address to parts
	 *
	 * @param string $address
	 * @return array
	 */
	public function get_address_components( $address ) {
		$result                    = array();
		$result['house_number']    = '';
		$result['number_addition'] = '';

		$address = str_replace( array( '?', '*', '[', ']', ',', '!' ), ' ', $address );
		$address = preg_replace( '/\s\s+/', ' ', $address );

		preg_match( '/^([0-9]*)(.*?)([0-9]+)(.*)/', $address, $matches );

		if ( ! empty( $matches[2] ) ) {
			$result['street']          = trim( $matches[1] . $matches[2] );
			$result['house_number']    = trim( $matches[3] );
			$result['number_addition'] = trim( $matches[4] );
		} else {
			$result['street'] = $address;
		}

		return $result;
	}

	public function get_house_number() {
		return $this->components['house_number'];
	}

	public function get_number_additional() {
		return $this->components['number_addition'];
	}

	public function get_street() {
		return $this->components['street'];
	}
}
