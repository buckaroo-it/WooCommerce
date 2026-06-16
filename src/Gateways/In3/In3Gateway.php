<?php

namespace Buckaroo\Woocommerce\Gateways\In3;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Traits\HasDateValidation;

class In3Gateway extends AbstractPaymentGateway
{
    use HasDateValidation;

    public const PAYMENT_CLASS = In3Processor::class;

    public $type;

    public $vattype;

    public $country;

    public function __construct()
    {
        $this->id = 'buckaroo_in3';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo In3';

        $this->title = 'In3';

        $this->setCountry();

        parent::__construct();

        $this->setIcon('svg/in3.svg');
        $this->addRefundSupport();
    }

    public function getServiceCode(?AbstractProcessor $processor = null)
    {
        return 'in3';
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @return void
     */
    public function validate_fields()
    {
        $birthdate = $this->request->input('buckaroo-in3-birthdate');

        $country = $this->request->input('billing_country');
        if ($country === null) {
            $country = $this->country;
        }

        if ($country === 'NL' && ! $this->validateDate($birthdate, 'd-m-Y')) {
            wc_add_notice(__('You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if (
            $this->request->input('billing_phone') === null &&
            $this->request->input('buckaroo-in3-phone') === null
        ) {
            wc_add_notice(
                sprintf(
                    __('Please fill in a phone number for %s. This is required in order to use this payment method.', 'wc-buckaroo-bpe-gateway'),
                    'In3'
                ),
                'error'
            );
        }

        parent::validate_fields();
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->add_financial_warning_field();
    }

    /**  {@inheritDoc} */
    protected function setProperties()
    {
        parent::setProperties();
        $this->type = 'in3';
        $this->vattype = $this->get_option('vattype');
    }
}
