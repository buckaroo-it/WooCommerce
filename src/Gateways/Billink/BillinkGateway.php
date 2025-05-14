<?php

namespace Buckaroo\Woocommerce\Gateways\Billink;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Traits\HasDateValidation;
use WC_Order;

class BillinkGateway extends AbstractPaymentGateway
{
    use HasDateValidation;

    public const PAYMENT_CLASS = BillinkProcessor::class;

    public $type;

    public $b2b;

    public $vattype;

    public $country;

    public $billinkpayauthorize;

    public bool $capturable = true;

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

    /**
     * Process payment
     *
     * @param  int  $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $processedPayment = parent::process_payment($order_id);

        if ($processedPayment['result'] == 'success' && $this->billinkpayauthorize == 'authorize') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
            $this->set_order_capture($order_id, 'Billink');
        }

        return $processedPayment;
    }

    /** {@inheritDoc} */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->add_financial_warning_field();
        $this->form_fields['billinkpayauthorize'] = [
            'title' => __('Billink Pay or Capture', 'wc-buckaroo-bpe-gateway'),
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
        $this->type = 'billink';
        $this->vattype = $this->get_option('vattype');
        $this->billinkpayauthorize = $this->get_option('billinkpayauthorize');
    }

    public function canShowCaptureForm($order): bool
    {
        $order = Helper::resolveOrder($order);

        if (! $order instanceof WC_Order) {
            return false;
        }

        return $this->billinkpayauthorize == 'authorize' && get_post_meta($order->get_id(), '_wc_order_authorized', true) == 'yes';
    }
}
