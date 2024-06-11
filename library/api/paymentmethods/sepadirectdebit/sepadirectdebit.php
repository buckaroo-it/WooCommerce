<?php

require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooSepaDirectDebit extends BuckarooPaymentMethod {
	public $customeraccountname;
	public $CustomerBIC;
	public $CustomerIBAN;

	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'sepadirectdebit';
		$this->version = '1';
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return void
	 */
	public function Pay( $customVars = array() ) {
		return null;
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return parent::Pay()
	 */
	public function PayDirectDebit() {

		$this->setCustomVar( 'customeraccountname', $this->customeraccountname );
		$this->setCustomVar( 'CustomerBIC', $this->CustomerBIC );
		$this->setCustomVar( 'CustomerIBAN', $this->CustomerIBAN );

		return parent::Pay();
	}
}
