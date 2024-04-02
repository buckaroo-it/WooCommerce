<?php

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_EPS extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_eps';
        $this->title                  = 'EPS';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo EPS";
        $this->set_icon('24x24/eps.png', 'svg/eps.svg');

        parent::__construct();
        $this->add_refund_support();
    }
}
