<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooPayconiq extends BuckarooPaymentMethod {

	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'payconiq';
		$this->version = 1;
	}
}
