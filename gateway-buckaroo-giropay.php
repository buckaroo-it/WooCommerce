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
        $this->setIcon('24x24/giropay.gif', 'svg/giropay.svg');

        parent::__construct();
        $this->addRefundSupport();
        apply_filters('buckaroo_init_payment_class', $this);
    }
}
