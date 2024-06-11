<?php

require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooTransfer extends BuckarooPaymentMethod {
	public function __construct() {
		$this->type    = 'transfer';
		$this->version = 1;
	}

	public function Pay( $customVars = array() ) {
		return null;
	}

	public function PayTransfer( $customVars ) {

		if ( isset( $customVars['CustomerFirstName'] ) ) {
			$this->setCustomVar( 'customerFirstName', $customVars['CustomerFirstName'] );
		}
		if ( isset( $customVars['CustomerLastName'] ) ) {
			$this->setCustomVar( 'customerLastName', $customVars['CustomerLastName'] );
		}
		if ( isset( $customVars['Customeremail'] ) ) {
			$this->setCustomVar( 'customeremail', $customVars['Customeremail'] );
		}
		if ( isset( $customVars['DateDue'] ) ) {
			$this->setCustomVar( 'DateDue', $customVars['DateDue'] );
		}
		if ( isset( $customVars['CustomerCountry'] ) ) {
			$this->setCustomVar( 'customercountry', $customVars['CustomerCountry'] );
		}
		$this->setCustomVar( 'SendMail', $customVars['SendMail'] );

		return parent::Pay( $customVars );
	}
}
