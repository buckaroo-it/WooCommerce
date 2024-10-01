<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods;

class Belfius extends PaymentGatewayHandler
{
    public function __construct()
    {
        $this->id = 'buckaroo_belfius';
        $this->title = 'Belfius';
        $this->has_fields = false;
        $this->method_title = "Buckaroo Belfius";
        $this->set_icon('24x24/belfius.png', 'svg/belfius.svg');

        parent::__construct();
        $this->add_refund_support();
    }
}