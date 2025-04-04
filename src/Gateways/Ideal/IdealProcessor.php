<?php

namespace Buckaroo\Woocommerce\Gateways\Ideal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class IdealProcessor extends AbstractPaymentProcessor {

	public $issuer;
	public $channel;
	protected $data;

	protected function getMethodBody(): array {
        return array(
            'continueOnIncomplete' => true,
        );
	}
}
