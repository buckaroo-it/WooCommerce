<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/sepadirectdebit/sepadirectdebit.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_SepaDirectDebit extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooSepaDirectDebit::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_sepadirectdebit';
        $this->title                  = 'SEPA Direct Debit';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo SEPA Direct Debit';
        $this->setIcon('24x24/directdebit.png', 'new/SEPA-directdebit.png', 'svg/SEPA-directdebit.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
   
    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return $this->processDefaultRefund(
            $order_id,
            $amount,
            $reason,
            false,
            function($request)
            {
                $request->channel   = BuckarooConfig::CHANNEL_BACKOFFICE;
            }
        );
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
        if (empty($_POST['buckaroo-sepadirectdebit-accountname'])
            || empty($_POST['buckaroo-sepadirectdebit-iban'])) {
            wc_add_notice(__("Please fill in all required fields", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        if (!BuckarooSepaDirectDebit::isIBAN($_POST['buckaroo-sepadirectdebit-iban'])) {
            wc_add_notice(__("Wrong IBAN number", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        parent::validate_fields();
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        if (empty($_POST['buckaroo-sepadirectdebit-accountname'])
            || empty($_POST['buckaroo-sepadirectdebit-iban'])) {
            wc_add_notice(__("Please fill in all required fields", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        };
        $order = getWCOrder($order_id);
        /** @var BuckarooSepaDirectDebit */
        $sepadirectdebit = $this->createDebitRequest($order);

        if (!$sepadirectdebit->isIBAN($_POST['buckaroo-sepadirectdebit-iban'])) {
            wc_add_notice(__("Wrong IBAN number", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }

        $sepadirectdebit->customeraccountname = $_POST['buckaroo-sepadirectdebit-accountname'];
        $sepadirectdebit->CustomerBIC         = $_POST['buckaroo-sepadirectdebit-bic'];
        $sepadirectdebit->CustomerIBAN        = $_POST['buckaroo-sepadirectdebit-iban'];

    
        $sepadirectdebit->returnUrl = $this->notify_url;
        $response                   = $sepadirectdebit->PayDirectDebit();
        return fn_buckaroo_process_response($this, $response, $this->mode);
    }

    /**
     * Check response data
     *
     * @access public
     */
    public function response_handler()
    {
        fn_buckaroo_process_response($this);
        exit;
    }
}
