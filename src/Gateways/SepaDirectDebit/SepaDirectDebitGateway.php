<?php

namespace Buckaroo\Woocommerce\Gateways\SepaDirectDebit;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class SepaDirectDebitGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = SepaDirectDebitProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_sepadirectdebit';
        $this->title = 'SEPA Direct Debit';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo SEPA Direct Debit';
        $this->setIcon('24x24/directdebit.png', 'svg/sepa-directdebit.svg');

        parent::__construct();
        $this->addRefundSupport();
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
            wc_add_notice(__('Please fill in all required fields', 'wc-buckaroo-bpe-gateway'), 'error');
        }
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        if (!SepaDirectDebitProcessor::isIBAN($iban)) {
            wc_add_notice(__('Wrong IBAN number', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        parent::validate_fields();
    }
}