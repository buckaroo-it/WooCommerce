<?php

namespace Buckaroo\Woocommerce\Gateways\Sofort;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class SofortGateway extends AbstractPaymentGateway
{

    public function __construct()
    {
        $this->id = 'buckaroo_sofortueberweisung';
        $this->title = 'Sofort';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Sofort';
        $this->setIcon('24x24/sofort.png', 'svg/sofort.svg');

        parent::__construct();
        $this->migrateOldSettings('woocommerce_buckaroo_sofortbanking_settings');
        $this->addRefundSupport();
        apply_filters('buckaroo_init_payment_class', $this);
    }
}