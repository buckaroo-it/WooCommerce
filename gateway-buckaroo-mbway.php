<?php

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_MBWay extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_mbway';
        $this->title                  = 'MBWay';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo MBWay";
        $this->set_icon('svg/mbway.svg', 'svg/mbway.svg');

        parent::__construct();
        $this->add_refund_support();
    }
}
