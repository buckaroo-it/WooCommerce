<?php

namespace Buckaroo\Woocommerce\Gateways\KnakenSettle;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class KnakenSettleGateway extends AbstractPaymentGateway {

	public function __construct() {
		$this->id           = 'buckaroo_knaken';
		$this->title        = 'Knaken Settle';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Knaken Settle';
		$this->setIcon( '24x24/knaken.png', 'svg/knaken.svg' );

		parent::__construct();
		$this->addRefundSupport();
	}
}
