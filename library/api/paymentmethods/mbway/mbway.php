<?php

require_once __DIR__ . '/../paymentmethod.php';

class BuckarooMBWay extends BuckarooPaymentMethod {
	public function __construct() {
		$this->type = 'MBWay';
	}
}
