<?php

namespace Buckaroo\Woocommerce\Gateways\Multibanco;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class MultibancoGateway extends AbstractPaymentGateway {

	public function __construct() {
		$this->id           = 'buckaroo_multibanco';
		$this->title        = 'Multibanco';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Multibanco';
		$this->setIcon( 'svg/multibanco.svg' );

		parent::__construct();
		$this->addRefundSupport();
	}
}
