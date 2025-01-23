<?php

namespace Buckaroo\Woocommerce\Gateways;

use WC_Payment_Gateway;

abstract class AbstractProcessor extends WC_Payment_Gateway {

	public AbstractPaymentGateway $gateway;

	abstract public function getAction(): string;

	/**
	 * Get ip
	 *
	 * @return string
	 */
	protected function getIp(): string {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED'] ) ) {
			$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
		} elseif ( ! empty( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
			$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['HTTP_FORWARDED'] ) ) {
			$ipaddress = $_SERVER['HTTP_FORWARDED'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		} else {
			$ipaddress = 'UNKNOWN';
		}
		$ex = explode( ',', sanitize_text_field( $ipaddress ) );
		if ( filter_var( $ex[0], FILTER_VALIDATE_IP ) ) {
			return trim( $ex[0] );
		}
		return '';
	}
}
