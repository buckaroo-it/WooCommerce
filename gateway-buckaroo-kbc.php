<?php

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_KBC extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_kbc';
        $this->title                  = 'KBC';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo KBC";
        $this->set_icon('24x24/kbc.png', 'svg/kbc.svg');

        parent::__construct();
        $this->add_refund_support();
        apply_filters('buckaroo_init_payment_class', $this);
    }
}
