<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;

class KlarnaPayGateway extends KlarnaGateway {

	function __construct() {
		$this->id           = 'buckaroo_klarnapay';
		$this->title        = 'Klarna: Pay later';
		$this->method_title = 'Buckaroo Klarna Pay later';

		$this->klarnaPaymentFlowId = 'pay';

		parent::__construct();
	}

	public function getServiceCode( ?AbstractProcessor $processor = null ) {
		return 'klarna';
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
