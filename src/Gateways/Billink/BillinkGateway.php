<?php

namespace Buckaroo\Woocommerce\Gateways\Billink;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Traits\HasDateValidation;

class BillinkGateway extends AbstractPaymentGateway
{
    use HasDateValidation;

    public const PAYMENT_CLASS = BillinkProcessor::class;

    public $type;

    public $b2b;

    public $vattype;

    public $country;

    public bool $capturable = false;

    public function __construct()
    {
        $this->id = 'buckaroo_billink';
        $this->title = 'Billink';
        $this->has_fields = true;
        $this->method_title = 'Buckaroo Billink';
        $this->setIcon('svg/billink.svg');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Validate fields
     *
     * @return void;
     */
    public function validate_fields()
    {
        if ($this->request->input('billing_company')) {
            if ($this->request->input('buckaroo-billink-company-coc-registration') === null) {
                wc_add_notice(__('Please enter correct COC (KvK) number', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            if (
                ! $this->validateDate($this->request->input('buckaroo-billink-birthdate'), 'd-m-Y')
            ) {
                wc_add_notice(__('Please enter correct birth date', 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if (! in_array($this->request->input('buckaroo-billink-gender'), ['Male', 'Female', 'Unknown'])) {
                wc_add_notice(__('Unknown gender', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if (! $this->request->input('buckaroo-billink-accept')) {
            wc_add_notice(__('Please accept license agreements', 'wc-buckaroo-bpe-gateway'), 'error');
        }
        parent::validate_fields();
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
        $this->type = 'billink';
        $this->vattype = $this->get_option('vattype');
    }
}
