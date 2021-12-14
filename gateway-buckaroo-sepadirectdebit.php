<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/sepadirectdebit/sepadirectdebit.php';

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
        $this->setIcon('24x24/directdebit.png', 'new/SEPA-directdebit.png');

        parent::__construct();
        $this->addRefundSupport();
    }
   
    /**
     * Can the order be refunded
     * @param object $order WC_Order
     * @return object & string
     */
    public function can_refund_order($order)
    {
        return $order && $order->get_transaction_id();
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
        if (version_compare(WC()->version, '3.6', '<')) {
            resetOrder();
        }
        return;
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

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_certificate'));

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_hide_local'));

        //Start Dynamic Rendering of Hidden Fields
        $options      = get_option("woocommerce_" . $this->id . "_settings", null);
        $ccontent_arr = array();
        $keybase      = 'certificatecontents';
        $keycount     = 1;
        if (!empty($options["$keybase$keycount"])) {
            while (!empty($options["$keybase$keycount"])) {
                $ccontent_arr[] = "$keybase$keycount";
                $keycount++;
            }
        }
        $while_key                 = 1;
        $selectcertificate_options = array('none' => 'None selected');
        while ($while_key != $keycount) {
            $this->form_fields["certificatecontents$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '',
            );
            $this->form_fields["certificateuploadtime$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $this->form_fields["certificatename$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $selectcertificate_options["$while_key"] = $options["certificatename$while_key"];

            $while_key++;
        }
        $final_ccontent                                          = $keycount;
        $this->form_fields["certificatecontents$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificateuploadtime$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificatename$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');

        $this->form_fields['selectcertificate'] = array(
            'title'       => __('Select Certificate', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Select your certificate by name.', 'wc-buckaroo-bpe-gateway'),
            'options'     => $selectcertificate_options,
            'default'     => 'none',
        );
        $this->form_fields['choosecertificate'] = array(
            'title'       => __('', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'file',
            'description' => __(''),
            'default'     => '');
      
    }
}
