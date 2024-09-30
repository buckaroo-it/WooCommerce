<?php

namespace WC_Buckaroo\WooCommerce\Capture\Methods;

use WC_Buckaroo\WooCommerce\Payment\Methods\Buckaroo_Default_Method;

class Buckaroo_Creditcard_Capture extends Buckaroo_Default_Method
{

    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return [];
    }


}
