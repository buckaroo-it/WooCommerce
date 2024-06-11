<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooP24 extends BuckarooPaymentMethod {

	public function __construct() {
		$this->type    = 'Przelewy24';
		$this->version = 1;
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::Pay();
	 */
	public function Pay( $customVars = array() ) {
		$this->setCustomVar(
			array(
				'CustomerEmail'     => array(
					'value' => $customVars['Customeremail'],
				),
				'CustomerFirstName' => array(
					'value' => $customVars['CustomerFirstName'],
				),
				'CustomerLastName'  => array(
					'value' => $customVars['CustomerLastName'],
				),

			)
		);

		return parent::Pay();
	}
}
