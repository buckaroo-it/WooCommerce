<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/giropay/giropay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Giropay extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooGiropay::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_giropay';
        $this->title                  = 'Giropay';
        $this->has_fields             = true;
        $this->method_title           = "Buckaroo Giropay";
        $this->setIcon('24x24/giropay.gif', 'new/Giropay.png');

        parent::__construct();

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
        $GLOBALS['plugin_id']            = $this->plugin_id . $this->id . '_settings';
        $order                           = wc_get_order($order_id);
        $giropay                         = new BuckarooGiropay();
        $giropay->amountDedit            = 0;
        $giropay->amountCredit           = $amount;
        $giropay->currency               = $this->currency;
        $giropay->description            = $reason;
        $giropay->invoiceId              = $order->get_order_number();
        $giropay->orderId                = $order_id;
        $giropay->OriginalTransactionKey = $order->get_transaction_id();
        $giropay->returnUrl              = $this->notify_url;
        $payment_type                    = str_replace('buckaroo_', '', strtolower($this->id));
        $giropay->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                        = null;

        $orderDataForChecking = $giropay->getOrderRefundData();

        try {
            $giropay->checkRefundData($orderDataForChecking);
            $response = $giropay->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if (empty($_POST['buckaroo-giropay-bancaccount'])) {
            wc_add_notice(__('Please provide correct BIC', 'wc-buckaroo-bpe-gateway'), 'error');
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
        if (empty($_POST['buckaroo-giropay-bancaccount'])) {
            wc_add_notice(__('Please provide correct BIC', 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }

        $order = getWCOrder($order_id);
        /** @var BuckarooGiropay */
        $giropay = $this->createDebitRequest($order);
        $giropay->bic         = $_POST['buckaroo-giropay-bancaccount'];
        $response = $giropay->Pay();
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
