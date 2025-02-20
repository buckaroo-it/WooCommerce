<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

class CartebleuevisaGateway extends SingleCreditCardGateway {

	public function setParameters() {
		$this->id           = 'buckaroo_creditcard_cartebleuevisa';
		$this->title        = 'Carte Bleue';
		$this->method_title = 'Buckaroo Carte Bleue';
	}
}
