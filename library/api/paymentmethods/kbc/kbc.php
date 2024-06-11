<?php

require_once __DIR__ . '/../paymentmethod.php';

class BuckarooKBC extends BuckarooPaymentMethod {
	public function __construct() {
		$this->type    = 'KBCPaymentButton';
		$this->version = 1;
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::Pay()
	 */
	public function Pay( $customVars = array() ) {
		return parent::Pay();
	}
}
