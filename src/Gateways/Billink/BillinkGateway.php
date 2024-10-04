<?php

namespace Buckaroo\Woocommerce\Gateways\Billink;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use DateTime;

class BillinkGateway extends AbstractPaymentGateway
{

    const PAYMENT_CLASS = BillinkProcessor::class;
    public $type;
    public $b2b;
    public $vattype;
    public $country;

    public function __construct()
    {

        $this->id = 'buckaroo_billink';
        $this->title = 'Billink';
        $this->has_fields = true;
        $this->method_title = 'Buckaroo Billink';
        $this->setIcon('24x24/billink.png', 'svg/billink.svg');
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
        if ($this->request('billing_company') !== null) {
            if ($this->request('buckaroo-billink-company-coc-registration') === null) {
                wc_add_notice(__('Please enter correct COC (KvK) number', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            if (!$this->validateDate($this->request('buckaroo-billink-birthdate'), 'd-m-Y')
            ) {
                wc_add_notice(__('Please enter correct birth date', 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if (!in_array($this->request('buckaroo-billink-gender'), array('Male', 'Female', 'Unknown'))) {
                wc_add_notice(__('Unknown gender', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if ($this->request('buckaroo-billink-accept') === null) {
            wc_add_notice(__('Please accept license agreements', 'wc-buckaroo-bpe-gateway'), 'error');
        }
        parent::validate_fields();
    }

    /**
     * Check that a date is valid.
     *
     * @param String $date A date expressed as a string
     * @param String $format The format of the date
     * @return Object Datetime
     * @return Boolean Format correct returns True, else returns false
     */
    public function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && ($d->format($format) == $date);
    }


    /** @inheritDoc */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->add_financial_warning_field();
    }

    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->type = 'billink';
        $this->vattype = $this->get_option('vattype');
    }
}