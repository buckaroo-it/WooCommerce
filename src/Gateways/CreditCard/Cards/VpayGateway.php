<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class VpayGateway extends SingleCreditCardGateway {

	public function setParameters() {
		$this->id           = 'buckaroo_creditcard_vpay';
		$this->title        = 'Vpay';
		$this->method_title = 'Buckaroo Vpay';
	}
}
