<?php
require_once 'library/include.php';
require_once dirname(__FILE__) . '/library/api/paymentmethods/giropay/giropay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Giropay extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $woocommerce                  = getWooCommerceObject();
        $this->id                     = 'buckaroo_giropay';
        $this->title                  = 'Giropay';
        $this->icon = apply_filters('woocommerce_buckaroo_giropay_icon', BuckarooConfig::getIconPath('24x24/giropay.gif', 'new/Giropay.png'));
        $this->has_fields             = true;
        $this->method_title           = "Buckaroo Giropay";
        $this->description            =  sprintf(__('Pay with %s', 'wc-buckaroo-bpe-gateway'), $this->title);
        $GLOBALS['plugin_id']         = $this->plugin_id . $this->id . '_settings';
        $this->currency               = get_woocommerce_currency();
        $this->secretkey              = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode                   = BuckarooConfig::getMode();
        $this->thumbprint             = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture                = BuckarooConfig::get('CULTURE');
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');
        parent::__construct();

        $this->supports = array(
            'products',
        );

        $this->notify_url = home_url('/');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_buckaroo_giropay', array($this, 'response_handler'));
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Giropay', $this->notify_url);
        }
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
        $woocommerce = getWooCommerceObject();

        if (empty($_POST['buckaroo-giropay-bancaccount'])) {
            wc_add_notice(__('Please provide correct BIC', 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = getWCOrder($order_id);
        $giropay              = new BuckarooGiropay();
        if (method_exists($order, 'get_order_total')) {
            $giropay->amountDedit = $order->get_order_total();
        } else {
            $giropay->amountDedit = $order->get_total();
        }
        $payment_type         = str_replace('buckaroo_', '', strtolower($this->id));
        $giropay->channel     = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $giropay->currency    = $this->currency;
        $giropay->description = $this->transactiondescription;
        $giropay->invoiceId   = (string) getUniqInvoiceId($order->get_order_number());
        $giropay->orderId     = (string) $order_id;
        $giropay->bic         = $_POST['buckaroo-giropay-bancaccount'];
        $giropay->returnUrl   = $this->notify_url;

        $response = $giropay->Pay();
        return fn_buckaroo_process_response($this, $response);
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
    }

}
