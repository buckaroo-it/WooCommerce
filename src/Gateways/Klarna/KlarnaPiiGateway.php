<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

class KlarnaPiiGateway extends KlarnaGateway
{
    function __construct()
    {
        $this->id = 'buckaroo_klarnapii';
        $this->title = 'Klarna: Slice it';
        $this->method_title = 'Buckaroo Klarna Slice it';
        $this->klarnaPaymentFlowId = 'PayInInstallments';

        parent::__construct();
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