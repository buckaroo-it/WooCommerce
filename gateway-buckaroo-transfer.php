<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/transfer/transfer.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Transfer extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooTransfer::class;
    public $datedue;
    public $sendemail;
    public $showpayproc;
    public function __construct()
    {
        $this->id                     = 'buckaroo_transfer';
        $this->title                  = 'Bank Transfer';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo Bank Transfer';
        $this->setIcon('24x24/transfer.jpg', 'new/SEPA-credittransfer.png');

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * @inheritDoc
     * 
     */
    protected function setProperties()
    {
        parent::setProperties();
        $this->datedue     = $this->get_option('datedue');
        $this->sendemail   = $this->get_option('sendmail');
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
        return $this->processDefaultRefund($order_id, $amount, $reason);
    }

    /**
     * Validate fields
     * @return void;
     */
    public function validate_fields()
    {
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
        $order = getWCOrder($order_id);
        /** @var BuckarooTransfer */
        $transfer = $this->createDebitRequest($order);

        $customVars = array();

        $get_billing_first_name          = getWCOrderDetails($order_id, 'billing_first_name');
        $get_billing_last_name           = getWCOrderDetails($order_id, 'billing_last_name');
        $get_billing_email               = getWCOrderDetails($order_id, 'billing_email');
        $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
        $customVars['CustomerLastName']  = !empty($get_billing_last_name) ? $get_billing_last_name : '';
        $customVars['Customeremail']     = !empty($get_billing_email) ? $get_billing_email : '';

        $customVars['SendMail'] = $this->sendemail;
        if ((int) $this->datedue > -1) {
            $customVars['DateDue'] = date('Y-m-d', strtotime('now + ' . (int) $this->datedue . ' day'));
        } else {
            $customVars['DateDue'] = date('Y-m-d', strtotime('now + 14 day'));
        }
        $customVars['CustomerCountry'] = getWCOrderDetails($order_id, "billing_country");

        $response = $transfer->PayTransfer($customVars);
        return fn_buckaroo_process_response($this, $response);
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
     * Print thank you description to the screen.
     *
     * @access public
     */
    public function thankyou_description()
    {
        if (!session_id()) {
            @session_start();
        }

        print $_SESSION['buckaroo_response'];
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

        $this->form_fields['datedue'] = array(
            'title'       => __('Number of days till order expire', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('Number of days to the date that the order should be payed.', 'wc-buckaroo-bpe-gateway'),
            'default'     => '14');
        $this->form_fields['sendmail'] = array(
            'title'       => __('Send email', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Buckaroo sends an email to the customer with the payment procedures.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Yes', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('No', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'FALSE');
        $this->form_fields['showpayproc'] = array(
            'title'       => __('Show payment procedures', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Show payment procedures on the thank you page after payment confirmation.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Yes', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('No', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'FALSE');
    }
}
