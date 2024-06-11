<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooCreditCard extends BuckarooPaymentMethod {

	public function __construct() {
		$this->version = 1;
	}

	/**
	 * @access public
	 * @return callable parent::Refund()
	 */
	public function Refund() {
		$this->setType(
			get_post_meta( $this->getRealOrderId(), '_wc_order_payment_issuer', true )
		);
		return parent::Refund();
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::PayGlobal()
	 */
	public function Pay( $customVars = array() ) {

		$this->setServiceTypeActionAndVersion(
			$customVars['CreditCardIssuer'],
			'Pay',
			BuckarooPaymentMethod::VERSION_ZERO
		);

		// add the flag
		update_post_meta( $this->getRealOrderId(), '_wc_order_authorized', 'yes' );

		return $this->PayGlobal();
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::PayGlobal()
	 */
	public function AuthorizeCC( $customVars, $order ) {

		$this->setServiceTypeActionAndVersion(
			$customVars['CreditCardIssuer'],
			'Authorize',
			BuckarooPaymentMethod::VERSION_ZERO
		);

		// add the flag
		update_post_meta( $order->get_id(), '_wc_order_authorized', 'yes' );

		return $this->PayGlobal();
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::PayGlobal()
	 */
	public function Capture( $customVars = array() ) {

		$this->setServiceTypeActionAndVersion(
			$customVars['CreditCardIssuer'],
			'Capture',
			BuckarooPaymentMethod::VERSION_ZERO
		);

		return $this->CaptureGlobal();
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::PayGlobal()
	 */
	public function PayEncrypt( $customVars = array() ) {

		$this->setServiceTypeActionAndVersion(
			$customVars['CreditCardIssuer'],
			'PayEncrypted',
			BuckarooPaymentMethod::VERSION_ZERO
		);

		$this->setCustomVar(
			'EncryptedCardData',
			$customVars['CreditCardDataEncrypted']
		);

		return $this->PayGlobal();
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::PayGlobal()
	 */
	public function AuthorizeEncrypt( $customVars, $order ) {

		$this->setServiceTypeActionAndVersion(
			$customVars['CreditCardIssuer'],
			'AuthorizeEncrypted',
			BuckarooPaymentMethod::VERSION_ZERO
		);

		$this->setCustomVar(
			'EncryptedCardData',
			$customVars['CreditCardDataEncrypted']
		);

		// add the flag
		update_post_meta( $order->get_id(), '_wc_order_authorized', 'yes' );

		return $this->PayGlobal();
	}
}
