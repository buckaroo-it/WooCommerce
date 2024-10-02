<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\Multibanco;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentGatewayHandler;

class MultibancoGateway extends PaymentGatewayHandler
{
    public function __construct()
    {
        $this->id = 'buckaroo_multibanco';
        $this->title = 'Multibanco';
        $this->has_fields = false;
        $this->method_title = "Buckaroo Multibanco";
        $this->set_icon('svg/multibanco.svg', 'svg/multibanco.svg');

        parent::__construct();
        $this->add_refund_support();
    }
}