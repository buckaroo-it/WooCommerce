<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\Klarna;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class KlarnaInProcessor extends PaymentProcessorHandler
{
    /** @inheritDoc */
    public function get_action(): string
    {
        return 'payInInstallments';
    }
}