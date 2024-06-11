<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooBelfius extends BuckarooPaymentMethod {

	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'belfius';
		$this->version = 0;
	}
}
