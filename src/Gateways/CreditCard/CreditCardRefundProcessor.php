<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;

class CreditCardRefundProcessor extends AbstractRefundProcessor
{
    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        return [
            'name' => OrderMeta::get($this->getOrder(), '_payment_method_transaction'),
        ];
    }
}
