<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaKpProcessor;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureAction;
use Buckaroo\Woocommerce\SDK\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;

class AfterpayOldGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = AfterpayOldProcessor::class;
    public $type;
    public $b2b;
    public $vattype;
    public $country;
    public $productQtyLoop = true;
    public $afterpaypayauthorize;

    public function __construct()
    {
        $this->id = 'buckaroo_afterpay';
        $this->title = 'Riverty';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Riverty (Old)';
        $this->setIcon('afterpay.png', 'svg/afterpay.svg');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }

    public function getServiceCode()
    {
        return 'afterpaydigiaccept';
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
     */
    public function validate_fields()
    {
        if ($this->request->input('buckaroo-afterpay-accept') === null) {
            wc_add_notice(__('Please accept licence agreements', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        $birthdate = $this->parseDate($this->request->input('buckaroo-afterpay-birthdate'));
        $b2b = $this->request->input('buckaroo-afterpay-b2b');
        if (!$this->validateDate($birthdate, 'd-m-Y') && $b2b != 'ON') {
            wc_add_notice(__('You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if ($b2b == 'ON') {
            if ($this->request->input('buckaroo-afterpay-company-coc-registration') === null) {
                wc_add_notice(__('Company registration number is required (KvK)', 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if ($this->request->input('buckaroo-afterpay-company-name') === null) {
                wc_add_notice(__('Company name is required', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if ($this->request->input('buckaroo-afterpay-phone') === null && $this->request->input('billing_phone') === null) {
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
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        if ($this->afterpaypayauthorize == 'authorize') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
            $this->set_order_capture($order_id, "Afterpay");
        }

        return parent::process_payment($order_id);
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->add_financial_warning_field();
        $this->form_fields['service'] = array(
            'title' => __('Select Riverty service', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Please select the service', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                'afterpayacceptgiro' => __('Offer customer to pay afterwards by SEPA Direct Debit.', 'wc-buckaroo-bpe-gateway'),
                'afterpaydigiaccept' => __('Offer customer to pay afterwards by digital invoice.', 'wc-buckaroo-bpe-gateway'),
            ),
            'default' => 'afterpaydigiaccept',
        );

        $this->form_fields['enable_bb'] = array(
            'title' => __('Enable B2B option for Riverty', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Enables or disables possibility to pay using company credentials', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                'enable' => 'Enable',
                'disable' => 'Disable',
            ),
            'default' => 'disable',
        );

        $this->form_fields['vattype'] = array(
            'title' => __('Default product VAT type', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Please select the default VAT type for your products', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                '1' => '1 = High rate',
                '2' => '2 = Low rate',
                '3' => '3 = Zero rate',
                '4' => '4 = Null rate',
                '5' => '5 = middle rate',
            ),
            'default' => '1',
        );

        $this->form_fields['afterpaypayauthorize'] = array(
            'title' => __('Riverty Pay or Capture', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                'pay' => 'Pay',
                'authorize' => 'Authorize',
            ),
            'default' => 'pay',
        );
    }

    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->afterpaypayauthorize = $this->get_option('afterpaypayauthorize', 'Pay');
        $this->type = $this->get_option('service');
        $this->b2b = $this->get_option('enable_bb');
        $this->vattype = $this->get_option('vattype');
    }

    public function process_capture()
    {
        $order_id = $this->request->input('order_id');

        if ($order_id === null || !is_numeric($order_id)) {
            return $this->create_capture_error(__('A valid order number is required'));
        }

        $capture_amount = $this->request->input('capture_amount');
        if ($capture_amount === null || !is_scalar($capture_amount)) {
            return $this->create_capture_error(__('A valid capture amount is required'));
        }

        $order = Helper::findOrder($order_id);
        $processor = $this->newPaymentProcessorInstance($order);/** @var KlarnaKpProcessor $payment */;
        $payment = new BuckarooClient($this->getMode());
        $res = $payment->process($processor, additionalData: ['amountDebit' => $capture_amount]);

        return (new CaptureAction())->handle(
            $res,
            $order,
            $this->currency,
        );
    }
}