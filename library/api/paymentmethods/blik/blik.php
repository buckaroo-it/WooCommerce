<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooBlik extends BuckarooPaymentMethod {
    public $channel;

	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'blik';
		$this->version = 0;
	}
}
