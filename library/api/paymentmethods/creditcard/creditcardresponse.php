<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once __DIR__ . '/../response.php';

/**
 * @package Buckaroo
 */
class BuckarooCreditCardResponse extends BuckarooResponse {
	public $cardNumberEnding = '';

	/**
	 * @access protected
	 */
	protected function _parseSoapResponseChild() {
	}

	/**
	 * @access protected
	 */
	protected function _parsePostResponseChild() {
		if ( isset( $_POST[ 'brq_service_' . $this->payment_method . '_CardNumberEnding' ] ) ) {
			$this->cardNumberEnding = $this->_setPostVariable( 'brq_service_' . $this->payment_method . '_CardNumberEnding' );
		}
	}
}
