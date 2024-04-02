<?php

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_P24 extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_przelewy24';
        $this->title                  = 'Przelewy24';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Przelewy24";
        $this->set_icon('24x24/p24.png', 'svg/przelewy24.svg');
        $this->migrate_old_setting('woocommerce_buckaroo_p24_settings');
        
        parent::__construct();
        $this->add_refund_support();
    }
}
