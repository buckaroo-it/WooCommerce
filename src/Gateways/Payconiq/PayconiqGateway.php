<?php

namespace Buckaroo\Woocommerce\Gateways\Payconiq;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class PayconiqGateway extends AbstractPaymentGateway {

	public function __construct() {
		$this->id           = 'buckaroo_payconiq';
		$this->title        = 'Payconiq';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Payconiq';
		$this->setIcon( 'svg/payconiq.svg' );

		parent::__construct();
		$this->addRefundSupport();
	}
}
