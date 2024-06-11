<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooSofortbanking extends BuckarooPaymentMethod {

	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'sofortueberweisung';
		$this->version = 1;
	}
}
