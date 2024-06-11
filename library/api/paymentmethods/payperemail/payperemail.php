<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooPayPerEmail extends BuckarooPaymentMethod {

	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'payperemail';
		$this->version = 1;
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::Pay()
	 */
	public function Pay( $customVars = array() ) {
		return null;
	}

	public function PaymentInvitation( $customVars = array() ) {

		$this->setServiceActionAndVersion( 'PaymentInvitation' );

		if ( ! empty( $customVars['PaymentMethodsAllowed'] ) ) {
			$this->setCustomVar( 'PaymentMethodsAllowed', $customVars['PaymentMethodsAllowed'] );
		}

		if ( isset( $customVars['CustomerGender'] ) ) {
			$this->setCustomVar( 'customergender', $customVars['CustomerGender'] );
		}
		if ( isset( $customVars['CustomerFirstName'] ) ) {
			$this->setCustomVar( 'customerFirstName', $customVars['CustomerFirstName'] );
		}
		if ( isset( $customVars['CustomerLastName'] ) ) {
			$this->setCustomVar( 'customerLastName', $customVars['CustomerLastName'] );
		}
		if ( isset( $customVars['Customeremail'] ) ) {
			$this->setCustomVar( 'customeremail', $customVars['Customeremail'] );
		}

		if ( isset( $customVars['merchantSendsEmail'] ) ) {
			$this->setCustomVar( 'merchantSendsEmail', $customVars['merchantSendsEmail'] );
		}

		if ( isset( $customVars['ExpirationDate'] ) ) {
			$this->setCustomVar( 'ExpirationDate', $customVars['ExpirationDate'] );
		}

		return $this->PayGlobal();
	}
}
