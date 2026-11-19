<?php

namespace Buckaroo\Woocommerce\Gateways\In3;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Traits\HasDateValidation;
use WC_Order;

class In3Gateway extends AbstractPaymentGateway
{
    use HasDateValidation;

    public const PAYMENT_CLASS = In3Processor::class;

    protected array $supportedCountries = ['NL'];

    public $type;

    public $vattype;

    public $country;

    public function __construct()
    {
        $this->id = 'buckaroo_in3';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo In3';
        $this->method_description = __('Interest-free payment in three instalments over 60 days, settled via iDEAL.', 'wc-buckaroo-bpe-gateway');
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
     * Validate the payment fields against the billing details of the order.
     *
     * @return array<string>
     */
    public function getOrderValidationErrors(WC_Order $order): array
    {
        $errors = [];

        if ($order->get_billing_country() === 'NL' && ! $this->validateBirthdate($this->request->input('buckaroo-in3-birthdate'))) {
            $errors[] = __('You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.', 'wc-buckaroo-bpe-gateway');
        }

        if ($this->getFieldOrFallback('buckaroo-in3-phone', $order->get_billing_phone()) === '') {
            $errors[] = sprintf(
                __('Please fill in a phone number for %s. This is required in order to use this payment method.', 'wc-buckaroo-bpe-gateway'),
                'In3'
            );
        }

        return $errors;
    }

    /**  {@inheritDoc} */
    protected function setProperties()
    {
        parent::setProperties();
        $this->type = 'in3';
        $this->vattype = $this->get_option('vattype');
    }
}
