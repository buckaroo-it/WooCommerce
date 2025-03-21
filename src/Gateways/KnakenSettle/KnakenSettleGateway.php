<?php

namespace Buckaroo\Woocommerce\Gateways\KnakenSettle;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class KnakenSettleGateway extends AbstractPaymentGateway {

	public function __construct() {
		$this->id           = 'buckaroo_knaken';
		$this->title        = 'goSettle';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo goSettle';
		$this->setIcon( 'svg/goSettle.svg' );

		parent::__construct();
		$this->addRefundSupport();
	}
}
