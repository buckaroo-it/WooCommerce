<?php

namespace Buckaroo\Woocommerce\Gateways\Idin;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;

class IdinGateway extends AbstractPaymentGateway {

	const PAYMENT_CLASS = IdinProcessor::class;
	public $issuer;

	public function getServiceCode( ?AbstractProcessor $processor = null ) {
		return 'idin';
	}


	public function getMode() {
		return ( get_option( 'woocommerce_buckaroo_mastersettings_settings' )['useidin'] ?? false ) == 'live' ? 'live' : 'test';
	}
}
