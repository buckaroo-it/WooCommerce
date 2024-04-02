<?php

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Mistercash extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_bancontactmrcash';
        $this->title                  = 'Bancontact';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo Bancontact';
        $this->set_icon('24x24/mistercash.png', 'svg/bancontact.svg');

        parent::__construct();
        $this->migrate_old_setting('woocommerce_buckaroo_mistercash_settings');
        $this->add_refund_support();
        apply_filters('buckaroo_init_payment_class', $this);
    }
}
