<?php

namespace Buckaroo\Woocommerce\Gateways\Eps;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class EpsGateway extends AbstractPaymentGateway {

    public function __construct() {
         $this->id          = 'buckaroo_eps';
        $this->title        = 'EPS';
        $this->has_fields   = false;
        $this->method_title = 'Buckaroo EPS';
        $this->setIcon( '24x24/eps.png', 'svg/eps.svg' );

        parent::__construct();
        $this->addRefundSupport();
    }
}
