<?php

/**
 * Core class for order items
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
class Buckaroo_Http_Request {

	/**
	 * Get clean $_POST data
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function request( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return;
		}
		$value = map_deep( $_POST[ $key ], 'sanitize_text_field' );
		if ( is_string( $value ) && strlen( $value ) === 0 ) {
			return;
		}
		return $value;
	}
	/**
	 * Get clean $_GET data
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function requestGet( $key ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			return;
		}
		$value = map_deep( $_GET[ $key ], 'sanitize_text_field' );
		if ( is_string( $value ) && strlen( $value ) === 0 ) {
			return;
		}
		return $value;
	}
}
