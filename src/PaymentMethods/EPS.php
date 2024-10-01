<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods;

class EPS extends PaymentGatewayHandler
{
    public function __construct()
    {
        $this->id = 'buckaroo_eps';
        $this->title = 'EPS';
        $this->has_fields = false;
        $this->method_title = "Buckaroo EPS";
        $this->set_icon('24x24/eps.png', 'svg/eps.svg');

        parent::__construct();
        $this->add_refund_support();
    }
}