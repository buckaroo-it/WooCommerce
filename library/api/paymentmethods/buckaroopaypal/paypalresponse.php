<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once __DIR__ . '/../response.php';

/**
 * @package Buckaroo
 */
class BuckarooPayPalResponse extends BuckarooResponse {
	public $payerEmail;
	public $payerCountry;
	public $payerStatus;
	public $payerFirstname;
	public $payerLastname;
	public $paypalTransactionID;

	public function string_service( string $key ) {
		$value = $this->_setPostVariable( 'brq_SERVICE_paypal_' . $key );
		if ( is_string( $value ) && strlen( trim( $value ) ) > 0 ) {
			return $value;
		}
	}

	public function is_paypal_express(): bool {
		return $this->_setPostVariable( 'ADD_is_paypal_express' ) == true;
	}

	/**
	 * @access protected
	 */
	protected function _parseSoapResponseChild() {
		$this->payerEmail          = '';
		$this->payerCountry        = '';
		$this->payerStatus         = '';
		$this->payerFirstname      = '';
		$this->payerLastname       = '';
		$this->paypalTransactionID = '';
	}

	/**
	 * @access protected
	 */
	protected function _parsePostResponseChild() {
		if ( isset( $_POST['brq_service_paypal_payerEmail'] ) ) {
			$this->payerEmail = $this->_setPostVariable( 'brq_service_paypal_payerEmail' );
		}
		if ( isset( $_POST['brq_service_paypal_payerCountry'] ) ) {
			$this->payerCountry = $this->_setPostVariable( 'brq_service_paypal_payerCountry' );
		}
		if ( isset( $_POST['brq_service_paypal_payerStatus'] ) ) {
			$this->payerStatus = $this->_setPostVariable( 'brq_service_paypal_payerStatus' );
		}
		if ( isset( $_POST['brq_service_paypal_payerFirstname'] ) ) {
			$this->payerFirstname = $this->_setPostVariable( 'brq_service_paypal_payerFirstname' );
		}
		if ( isset( $_POST['brq_service_paypal_payerLastname'] ) ) {
			$this->payerLastname = $this->_setPostVariable( 'brq_service_paypal_payerLastname' );
		}
		if ( isset( $_POST['brq_service_paypal_paypalTransactionID'] ) ) {
			$this->paypalTransactionID = $this->_setPostVariable( 'brq_service_paypal_paypalTransactionID' );
		}
	}
}
