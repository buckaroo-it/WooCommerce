<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/eps/eps.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_EPS extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_eps';
        $this->title                  = 'EPS';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo EPS";
        $this->setIcon('24x24/eps.png', 'new/EPS.png');

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
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = wc_get_order($order_id);

        $eps                         = new BuckarooEPS();
        $eps->amountDedit            = 0;
        $eps->amountCredit           = $amount;
        $eps->currency               = $this->currency;
        $eps->description            = $reason;
        $eps->invoiceId              = $order->get_order_number();
        $eps->orderId                = $order_id;
        $eps->OriginalTransactionKey = $order->get_transaction_id();
        $eps->returnUrl              = $this->notify_url;
        $payment_type                = str_replace('buckaroo_', '', strtolower($this->id));
        $eps->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                    = null;

        $orderDataForChecking = $eps->getOrderRefundData();
        try {
            $eps->checkRefundData($orderDataForChecking);
            $response = $eps->Refund();
        } catch (Exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
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
        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = getWCOrder($order_id);
        $eps                  = new BuckarooEPS();
        if (method_exists($order, 'get_order_total')) {
            $eps->amountDedit = $order->get_order_total();
        } else {
            $eps->amountDedit = $order->get_total();
        }
        $payment_type     = str_replace('buckaroo_', '', strtolower($this->id));
        $eps->channel     = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $eps->currency    = $this->currency;
        $eps->description = $this->transactiondescription;

        $eps->invoiceId = getUniqInvoiceId($order->get_order_number());
        $eps->orderId   = (string) $order_id;
        $eps->returnUrl = $this->notify_url;
        $customVars     = array();

        if ($this->usenotification == 'TRUE') {
            $eps->usenotification         = 1;
            $customVars['Customergender'] = 0;

            $get_billing_first_name          = getWCOrderDetails($order_id, 'billing_first_name');
            $get_billing_last_name           = getWCOrderDetails($order_id, 'billing_last_name');
            $get_billing_email               = getWCOrderDetails($order_id, 'billing_email');
            $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['CustomerLastName']  = !empty($get_billing_last_name) ? $get_billing_last_name : '';
            $customVars['Customeremail']     = !empty($get_billing_email) ? $get_billing_email : '';

            $customVars['Notificationtype']  = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int) $this->notificationdelay . ' day'))));
        } else {
            $get_shipping_first_name         = getWCOrderDetails($order_id, 'billing_first_name');
            $get_shipping_last_name          = getWCOrderDetails($order_id, 'billing_last_name');
            $get_shipping_email              = getWCOrderDetails($order_id, 'billing_email');
            $customVars['customerEmail']     = !empty($get_shipping_email) ? $get_shipping_email : '';
            $customVars['CustomerFirstName'] = !empty($get_shipping_first_name) ? $get_shipping_first_name : '';
            $customVars['CustomerLastName']  = !empty($get_shipping_last_name) ? $get_shipping_last_name : '';
        }

        $response = $eps->Pay($customVars);
        return fn_buckaroo_process_response($this, $response);
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {

    }

    /**
     * Check response data
     *
     * @access public
     */
    public function response_handler()
    {
        $woocommerce          = getWooCommerceObject();
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $result               = fn_buckaroo_process_response($this);
        if (!is_null($result)) {
            wp_safe_redirect($result['redirect']);
        } else {
            wp_safe_redirect($this->get_failed_url());
        }
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

        $this->form_fields['usenotification'] = array(
            'title'       => __('Use Notification Service', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('The notification service can be used to have the payment engine sent additional notifications.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Yes', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('No', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'FALSE');

        $this->form_fields['notificationdelay'] = array(
            'title'       => __('Notification delay', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('The time at which the notification should be sent. If this is not specified, the notification is sent immediately.', 'wc-buckaroo-bpe-gateway'),
            'default'     => '0');
    }

}
