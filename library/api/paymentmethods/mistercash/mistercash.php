<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooMisterCash extends BuckarooPaymentMethod {

	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'bancontactmrcash';
		$this->version = 1;
	}
}
