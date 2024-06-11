<?php

require_once __DIR__ . '/../paymentmethod.php';

class BuckarooMultibanco extends BuckarooPaymentMethod {
	public function __construct() {
		$this->type = 'Multibanco';
	}
}
