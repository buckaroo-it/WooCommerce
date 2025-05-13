<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;

class KlarnaPiiGateway extends KlarnaGateway
{
    public function __construct()
    {
        $this->id = 'buckaroo_klarnapii';
        $this->title = 'Klarna: Slice it';
        $this->method_title = 'Buckaroo Klarna Slice it';
        $this->klarnaPaymentFlowId = 'PayInInstallments';

        parent::__construct();
    }

    public function getServiceCode(?AbstractProcessor $processor = null)
    {
        return 'klarna';
    }

    /**
     * Payment form on checkout page
     *
     * @return void
     */
    public function payment_fields()
    {
        $this->renderTemplate('buckaroo_klarnapay');
    }
}
