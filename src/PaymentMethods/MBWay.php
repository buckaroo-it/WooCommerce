<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods;

class MBWay extends PaymentGatewayHandler
{
    public function __construct()
    {
        $this->id = 'buckaroo_mbway';
        $this->title = 'MBWay';
        $this->has_fields = false;
        $this->method_title = "Buckaroo MBWay";
        $this->set_icon('svg/mbway.svg', 'svg/mbway.svg');

        parent::__construct();
        $this->add_refund_support();
    }
}