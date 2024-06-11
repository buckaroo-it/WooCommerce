<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooGiropay extends BuckarooPaymentMethod {
	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'giropay';
		$this->version = 2;
	}
}
