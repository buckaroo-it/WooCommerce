<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class KlarnaGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = KlarnaProcessor::class;

    protected $type;

    protected $vattype;

    public function __construct()
    {
        $this->has_fields = true;
        $this->type = 'klarna';
        $this->setIcon('svg/klarna.svg');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }

    public function getKlarnaSelector()
    {
        return str_replace('_', '-', $this->id);
    }

    /** {@inheritDoc} */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->add_financial_warning_field();
    }

    /**  {@inheritDoc} */
    protected function setProperties()
    {
        parent::setProperties();
        $this->vattype = $this->get_option('vattype');
    }
}
