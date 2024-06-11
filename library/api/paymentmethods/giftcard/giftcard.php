<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooGiftCard extends BuckarooPaymentMethod {
	public $cardtype = '';

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::Pay()
	 */
	public function Pay( $customVars = array() ) {

		$servicesSelectableByClient = BuckarooConfig::get( 'BUCKAROO_GIFTCARD_ALLOWED_CARDS' );

		if ( ! empty( $customVars['servicesSelectableByClient'] ) ) {
			$servicesSelectableByClient = $customVars['servicesSelectableByClient'];
		}
		$this->setCustomVarWithoutType(
			array(
				'servicesSelectableByClient' => $servicesSelectableByClient,
				'continueOnIncomplete'       => 'RedirectToHTML',
			)
		);

		$this->data['services'] = array();
		return parent::PayGlobal();
	}
}
