<?php

namespace WC_Buckaroo\WooCommerce\Capture\Methods;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class Buckaroo_Creditcard_Capture extends PaymentProcessorHandler
{

    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return [];
    }


}
