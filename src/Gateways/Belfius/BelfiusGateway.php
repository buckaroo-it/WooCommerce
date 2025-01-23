<?php

namespace Buckaroo\Woocommerce\Gateways\Belfius;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class BelfiusGateway extends AbstractPaymentGateway {

	public function __construct() {
		$this->id           = 'buckaroo_belfius';
		$this->title        = 'Belfius';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Belfius';
		$this->setIcon( '24x24/belfius.png', 'svg/belfius.svg' );

		parent::__construct();
		$this->addRefundSupport();
	}
}
