<?php

require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooApplepay extends BuckarooPaymentMethod {
	protected $data;

	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'applepay';
		$this->version = 0;
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::Pay();
	 */
	public function Pay( $customVars = array() ) {
		$this->setCustomVar(
			array(
				'PaymentData'      => $customVars['PaymentData'],
				'CustomerCardName' => $customVars['CustomerCardName'],
			)
		);

		return parent::Pay();
	}
}
