<?php


require_once __DIR__ . '/library/api/paymentmethods/klarna/klarna.php';
require_once __DIR__ . '/gateway-buckaroo-klarna.php';

class WC_Gateway_Buckaroo_KlarnaPay extends WC_Gateway_Buckaroo_Klarna {
	function __construct() {
		$this->id           = 'buckaroo_klarnapay';
		$this->title        = 'Klarna: Pay later';
		$this->method_title = 'Buckaroo Klarna Pay later';

		$this->klarnaPaymentFlowId = 'pay';

		parent::__construct();
	}
	/**
	 * Payment form on checkout page
	 *
	 * @return void
	 */
	public function payment_fields() {
		$this->renderTemplate();
	}
}
