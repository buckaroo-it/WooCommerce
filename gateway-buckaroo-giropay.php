<?php


/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Giropay extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_giropay';
        $this->title                  = 'Giropay';
        $this->has_fields             = true;
        $this->method_title           = "Buckaroo Giropay";
        $this->set_icon('24x24/giropay.gif', 'svg/giropay.svg');

        parent::__construct();
        $this->add_refund_support();
        apply_filters('buckaroo_init_payment_class', $this);
    }
}
