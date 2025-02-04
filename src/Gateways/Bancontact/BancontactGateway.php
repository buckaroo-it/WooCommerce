<?php

namespace Buckaroo\Woocommerce\Gateways\Bancontact;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class BancontactGateway extends AbstractPaymentGateway {

	public function __construct() {
		$this->id           = 'buckaroo_bancontactmrcash';
		$this->title        = 'Bancontact';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Bancontact';
		$this->setIcon( 'svg/bancontact.svg' );

		parent::__construct();
		$this->migrateOldSettings( 'woocommerce_buckaroo_mistercash_settings' );
		$this->addRefundSupport();
		apply_filters( 'buckaroo_init_payment_class', $this );
	}
}
