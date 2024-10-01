<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods;

class Sofortbanking extends PaymentGatewayHandler
{
    public function __construct()
    {
        $this->id = 'buckaroo_sofortueberweisung';
        $this->title = 'Sofort';
        $this->has_fields = false;
        $this->method_title = "Buckaroo Sofort";
        $this->set_icon('24x24/sofort.png', 'svg/sofort.svg');

        parent::__construct();
        $this->migrate_old_setting('woocommerce_buckaroo_sofortbanking_settings');
        $this->add_refund_support();
        apply_filters('buckaroo_init_payment_class', $this);
    }

}