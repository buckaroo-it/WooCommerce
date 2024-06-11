<?php


require_once __DIR__ . '/library/api/paymentmethods/klarna/klarna.php';
require_once __DIR__ . '/gateway-buckaroo-klarna.php';

class WC_Gateway_Buckaroo_KlarnaPII extends WC_Gateway_Buckaroo_Klarna {
	function __construct() {
		$this->id                  = 'buckaroo_klarnapii';
		$this->title               = 'Klarna: Slice it';
		$this->method_title        = 'Buckaroo Klarna Slice it';
		$this->klarnaPaymentFlowId = 'PayInInstallments';

		parent::__construct();
	}
	/**
	 * Payment form on checkout page
	 *
	 * @return void
	 */
	public function payment_fields() {
		$this->renderTemplate( 'buckaroo_klarnapay' );
	}
}
