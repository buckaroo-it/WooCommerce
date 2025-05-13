<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Traits\HasDateValidation;
use WC_Order;

class AfterpayOldGateway extends AbstractPaymentGateway
{
    use HasDateValidation;

    public const PAYMENT_CLASS = AfterpayOldProcessor::class;

    public $type;

    public $b2b;

    public $vattype;

    public $country;

    public $afterpaypayauthorize;

    public bool $capturable = true;

    public function __construct()
    {
        $this->id = 'buckaroo_afterpay';
        $this->title = 'Riverty';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Riverty (Old)';
        $this->setIcon('svg/afterpay.svg');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }

    public function getServiceCode(?AbstractProcessor $processor = null)
    {
        return 'afterpaydigiaccept';
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @return void
     */
    public function validate_fields()
    {
        if (! $this->request->input('buckaroo-afterpay-accept')) {
            wc_add_notice(__('Please accept licence agreements', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        $birthdate = $this->parseDate($this->request->input('buckaroo-afterpay-birthdate'));
        $b2b = $this->request->input('buckaroo-afterpay-b2b');
        if (! $this->validateDate($birthdate, 'd-m-Y') && $b2b != 'ON') {
            wc_add_notice(__('You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if ($b2b == 'ON') {
            if (! $this->request->input('buckaroo-afterpay-company-coc-registration')) {
                wc_add_notice(__('Company registration number is required (KvK)', 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if (! $this->request->input('buckaroo-afterpay-company-name')) {
                wc_add_notice(__('Company name is required', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if (! $this->request->input('buckaroo-afterpay-phone') && ! $this->request->input('billing_phone')) {
            wc_add_notice(__('Please enter phone number', 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if ($this->type == 'afterpayacceptgiro') {
            if ($this->request->input('buckaroo-afterpay-company-coc-registration') === null) {
                wc_add_notice(__('IBAN is required', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        parent::validate_fields();
    }

    /**
     * Process payment
     *
     * @param  int  $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $processedPayment = parent::process_payment($order_id);

        if ($processedPayment['result'] == 'success' && $this->afterpaypayauthorize == 'authorize') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
            $this->set_order_capture($order_id, 'Afterpay');
        }

        return $processedPayment;
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->add_financial_warning_field();
        $this->form_fields['service'] = [
            'title' => __('Select Riverty service', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Please select the service', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'afterpayacceptgiro' => __('Offer customer to pay afterwards by SEPA Direct Debit.', 'wc-buckaroo-bpe-gateway'),
                'afterpaydigiaccept' => __('Offer customer to pay afterwards by digital invoice.', 'wc-buckaroo-bpe-gateway'),
            ],
            'default' => 'afterpaydigiaccept',
        ];

        $this->form_fields['enable_bb'] = [
            'title' => __('Enable B2B option for Riverty', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Enables or disables possibility to pay using company credentials', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'enable' => 'Enable',
                'disable' => 'Disable',
            ],
            'default' => 'disable',
        ];

        $this->form_fields['vattype'] = [
            'title' => __('Default product VAT type', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Please select the default VAT type for your products', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                '1' => '1 = High rate',
                '2' => '2 = Low rate',
                '3' => '3 = Zero rate',
                '4' => '4 = Null rate',
                '5' => '5 = middle rate',
            ],
            'default' => '1',
        ];

        $this->form_fields['afterpaypayauthorize'] = [
            'title' => __('Riverty Pay or Capture', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'pay' => 'Pay',
                'authorize' => 'Authorize',
            ],
            'default' => 'pay',
        ];
    }

    /**  {@inheritDoc} */
    protected function setProperties()
    {
        parent::setProperties();
        $this->afterpaypayauthorize = $this->get_option('afterpaypayauthorize', 'Pay');
        $this->type = $this->get_option('service');
        $this->b2b = $this->get_option('enable_bb');
        $this->vattype = $this->get_option('vattype');
    }

    public function canShowCaptureForm($order): bool
    {
        $order = Helper::resolveOrder($order);

        if (! $order instanceof WC_Order) {
            return false;
        }

        return $this->afterpaypayauthorize == 'authorize' && get_post_meta($order->get_id(), '_wc_order_authorized', true) == 'yes';
    }
}
