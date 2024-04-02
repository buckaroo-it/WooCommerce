<?php

require_once(dirname(__FILE__) . '/library/api/paymentmethods/paybybank/paybybank.php');

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_PayByBank extends WC_Gateway_Buckaroo
{

    public function __construct()
    {
        $this->id = 'buckaroo_paybybank';
        $this->title = 'PayByBank';
        $this->has_fields   = true;
        $this->method_title = "Buckaroo PayByBank";
        $this->set_icon('24x24/paybybank.gif', 'svg/paybybank.gif');

        parent::__construct();
        $this->add_refund_support();
        apply_filters('buckaroo_init_payment_class', $this);
    }
  
    /**
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        $issuer = $this->request('buckaroo-paybybank-issuer');

        if ($issuer === null) {
            wc_add_notice(__("<strong>PayByBank </strong> is a required field.", 'wc-buckaroo-bpe-gateway'), 'error');
        } else
        if (!in_array($issuer, array_keys(BuckarooPayByBank::getIssuerList()))) {
            wc_add_notice(__("A valid PayByBank is required.", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        parent::validate_fields();
    }


    public function init_form_fields()
    {
        parent::init_form_fields();


        $this->form_fields['displaymode'] = [
            'title'             => __('Bank selection display', 'wc-buckaroo-bpe-gateway'),
            'type'              => 'select',
            'options'           => ['radio' => __('Radio button'), 'dropdown' => __('Dropdown')],
            'default'           => 'radio',
        ];

        unset($this->form_fields['extrachargeamount']); // no fee for this payment method
    }
}
