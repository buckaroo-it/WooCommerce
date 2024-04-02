<?php

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_SepaDirectDebit extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_sepadirectdebit';
        $this->title                  = 'SEPA Direct Debit';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo SEPA Direct Debit';
        $this->set_icon('24x24/directdebit.png', 'svg/sepa-directdebit.svg');

        parent::__construct();
        $this->add_refund_support();
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
        $iban = $this->request('buckaroo-sepadirectdebit-iban');
        if (
            $this->request('buckaroo-sepadirectdebit-accountname') === null ||
            $iban === null
            ) {
            wc_add_notice(__("Please fill in all required fields", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        // if (!BuckarooSepaDirectDebit::isIBAN($iban)) {
        //     wc_add_notice(__("Wrong IBAN number", 'wc-buckaroo-bpe-gateway'), 'error');
        // }

        parent::validate_fields();
    }
}
