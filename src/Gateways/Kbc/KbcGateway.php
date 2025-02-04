<?php

namespace Buckaroo\Woocommerce\Gateways\Kbc;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class KbcGateway extends AbstractPaymentGateway {

	public function __construct() {
		$this->id           = 'buckaroo_kbc';
		$this->title        = 'KBC';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo KBC';
		$this->setIcon( 'svg/kbc.svg' );

		parent::__construct();
		$this->addRefundSupport();
		apply_filters( 'buckaroo_init_payment_class', $this );
	}
}
