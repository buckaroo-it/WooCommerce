<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;

class CreditCardRefundProcessor extends AbstractRefundProcessor {

	/** @inheritDoc */
	protected function getMethodBody(): array {
		return array(
			'name' => get_post_meta( $this->getOrder()->get_id(), '_payment_method_transaction', true ),
		);
	}
}
