<?php

namespace Buckaroo\Woocommerce\Gateways\Przelewy24;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class Przelewy24Gateway extends AbstractPaymentGateway {

	const PAYMENT_CLASS = Przelewy24Processor::class;

	protected array $supportedCurrencies = array( 'PLN' );

	public function __construct() {
		$this->id           = 'buckaroo_przelewy24';
		$this->title        = 'Przelewy24';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Przelewy24';
		$this->setIcon( '24x24/p24.png', 'svg/przelewy24.svg' );
		$this->migrateOldSettings( 'woocommerce_buckaroo_p24_settings' );

		parent::__construct();
		$this->addRefundSupport();
	}
}
