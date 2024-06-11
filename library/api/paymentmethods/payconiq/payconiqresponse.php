<?php

require_once __DIR__ . '/../response.php';

/**
 * @package Buckaroo
 */
class BuckarooPayconiqResponse extends BuckarooResponse {
	public $paylink = '';

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
