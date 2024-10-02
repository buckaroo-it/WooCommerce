<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\KnakenSettle;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentGatewayHandler;

class KnakenSettleGateway extends PaymentGatewayHandler
{
    public function __construct()
    {
        $this->id = 'buckaroo_knaken';
        $this->title = 'Knaken Settle';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Knaken Settle';
        $this->set_icon('24x24/knaken.png', 'svg/knaken.svg');

        parent::__construct();
        $this->add_refund_support();
    }
}