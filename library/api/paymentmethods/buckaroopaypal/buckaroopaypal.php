<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooPayPal extends BuckarooPaymentMethod {
	public function __construct() {
		$this->type    = 'paypal';
		$this->version = 1;
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::Pay()
	 */
	public function Pay( $customVars = array() ) {
		if ( isset( $customVars['PayPalOrderId'] ) ) {
			$this->setCustomVar( 'PayPalOrderId', $customVars['PayPalOrderId'] );
			$this->setAdditionalParameters( 'is_paypal_express', true );
			return parent::Pay();
		}

		if ( $this->sellerprotection ) {
			$this->setService( 'action2', 'extraInfo' );
			$this->setService( 'version2', $this->version );

			$this->setCustomVar(
				array(
					'Name'            => mb_substr( $customVars['CustomerName'], 0, 32 ),
					'Street1'         => mb_substr( $customVars['ShippingStreet'] . ' ' . $customVars['ShippingHouse'], 0, 100 ),
					'CityName'        => mb_substr( $customVars['ShippingCity'], 0, 40 ),
					'StateOrProvince' => $customVars['StateOrProvince'],
					'PostalCode'      => mb_substr( $customVars['ShippingPostalCode'], 0, 20 ),
					'Country'         => $customVars['Country'],
					'AddressOverride' => 'TRUE',
				)
			);

		}

		return parent::Pay();
	}
}
