<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/p24/p24.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_P24 extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooP24::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_przelewy24';
        $this->title                  = 'P24';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo P24";
        $this->setIcon('24x24/p24.png', 'new/Przelewy24.png');
        $this->migrateOldSettings('woocommerce_buckaroo_p24_settings');
        
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
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = wc_get_order($order_id);

        $p24                         = new BuckarooP24();
        $p24->amountDedit            = 0;
        $p24->amountCredit           = $amount;
        $p24->currency               = $this->currency;
        $p24->description            = $reason;
        $p24->invoiceId              = $order->get_order_number();
        $p24->orderId                = $order_id;
        $p24->OriginalTransactionKey = $order->get_transaction_id();
        $p24->returnUrl              = $this->notify_url;
        $clean_order_no              = (int) str_replace('#', '', $order->get_order_number());
        $p24->setType(get_post_meta($clean_order_no, '_payment_method_transaction', true));
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $p24->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response     = null;

        $orderDataForChecking = $p24->getOrderRefundData();

        try {
            $p24->checkRefundData($orderDataForChecking);
            $response = $p24->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
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
        /** @var BuckarooP24 */
        $p24 = $this->createDebitRequest($order);
        $get_shipping_first_name         = getWCOrderDetails($order_id, 'billing_first_name');
        $get_shipping_last_name          = getWCOrderDetails($order_id, 'billing_last_name');
        $get_shipping_email              = getWCOrderDetails($order_id, 'billing_email');
        $customVars['Customeremail']     = !empty($get_shipping_email) ? $get_shipping_email : '';
        $customVars['CustomerFirstName'] = !empty($get_shipping_first_name) ? $get_shipping_first_name : '';
        $customVars['CustomerLastName']  = !empty($get_shipping_last_name) ? $get_shipping_last_name : '';
        $response = $p24->Pay($customVars);
        return fn_buckaroo_process_response($this, $response);
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
