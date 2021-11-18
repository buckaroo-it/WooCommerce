<?php
require_once 'library/include.php';
require_once dirname(__FILE__) . '/library/api/paymentmethods/giftcard/giftcard.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Giftcard extends WC_Gateway_Buckaroo
{
    public $giftcards;

    public function __construct()
    {
        $woocommerce                  = getWooCommerceObject();
        $this->id                     = 'buckaroo_giftcard';
        $this->title                  = 'Giftcards';
        $this->icon = apply_filters('woocommerce_buckaroo_giftcard_icon', BuckarooConfig::getIconPath('24x24/giftcard.gif', 'new/Giftcards.png'));
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Giftcards";
        $this->description            =  sprintf(__('Pay with %s', 'wc-buckaroo-bpe-gateway'), $this->title);
        $GLOBALS['plugin_id']         = $this->plugin_id . $this->id . '_settings';
        $this->currency               = get_woocommerce_currency();
        $this->secretkey              = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode                   = BuckarooConfig::getMode();
        $this->thumbprint             = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture                = BuckarooConfig::get('CULTURE');
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');
        $this->usenotification        = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay      = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');

        parent::__construct();
        if (isset($this->settings['giftcards'])) {
            $this->giftcards = $this->settings['giftcards'];
        } else {
            $this->giftcards = null;
        }

        $this->supports = array(
            'products',
            'refunds',
        );

        $this->notify_url = home_url('/');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_buckaroo_giftcard', array($this, 'response_handler'));
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Giftcard', $this->notify_url);
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
        $GLOBALS['plugin_id']             = $this->plugin_id . $this->id . '_settings';
        $order                            = wc_get_order($order_id);
        $giftcard                         = new BuckarooGiftCard();
        $giftcard->amountDedit            = 0;
        $giftcard->amountCredit           = $amount;
        $giftcard->currency               = $this->currency;
        $giftcard->description            = $reason;
        $giftcard->invoiceId              = $order->get_order_number();
        $giftcard->orderId                = $order_id;
        $giftcard->OriginalTransactionKey = $order->get_transaction_id();
        $giftcard->returnUrl              = $this->notify_url;
        $clean_order_no                   = (int) str_replace('#', '', $order->get_order_number());
        $giftcard->setType(get_post_meta($clean_order_no, '_payment_method_transaction', true));
        $giftcard->version = 1;
        $payment_type      = str_replace('buckaroo_', '', strtolower($this->id));
        $giftcard->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response          = null;

        $orderDataForChecking = $giftcard->getOrderRefundData();

        try {
            $giftcard->checkRefundData($orderDataForChecking);
            $response = $giftcard->Refund();
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
        $giftcard             = new BuckarooGiftCard();

        if (method_exists($order, 'get_order_total')) {
            $giftcard->amountDedit = $order->get_order_total();
        } else {
            $giftcard->amountDedit = $order->get_total();
        }
        $payment_type          = str_replace('buckaroo_', '', strtolower($this->id));
        $giftcard->channel     = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $giftcard->currency    = $this->currency;
        $giftcard->description = $this->transactiondescription;
        $giftcard->invoiceId   = (string) getUniqInvoiceId($order->get_order_number());
        $giftcard->orderId     = (string) $order_id;
        $giftcard->returnUrl   = $this->notify_url;
        $customVars            = array();

        $customVars['servicesSelectableByClient'] = $this->giftcards;

        if ($this->usenotification == 'TRUE') {
            $giftcard->usenotification    = 1;
            $customVars['Customergender'] = 0;

            $get_billing_first_name          = getWCOrderDetails($order_id, 'billing_first_name');
            $get_billing_last_name           = getWCOrderDetails($order_id, 'billing_last_name');
            $get_billing_email               = getWCOrderDetails($order_id, 'billing_email');
            $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['CustomerLastName']  = !empty($get_billing_last_name) ? $get_billing_last_name : '';
            $customVars['Customeremail']     = !empty($get_billing_email) ? $get_billing_email : '';
            $customVars['Notificationtype']  = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int) $this->notificationdelay . ' day'))));
        }
        $response = $giftcard->Pay($customVars);
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
        if (isset($_GET['section']) && $_GET['section'] == 'buckaroo_giftcard'): ?>
            <div class="notice notice-info">
                <p>
                    <?php echo __("<b>Important:</b> Group giftcards can only be refunded from the Buckaroo Plaza.", 'wc-buckaroo-bpe-gateway'); ?>
                </p>
            </div>
        <?php endif;
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

        $this->form_fields['giftcards'] = array(
            'title'       => __('List of authorized giftcards', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('Giftcards must be comma separated', 'wc-buckaroo-bpe-gateway'),
            'default'     => 'westlandbon,ideal,ippies,babygiftcard,babyparkgiftcard,beautywellness,boekenbon,boekenvoordeel,designshopsgiftcard,fashioncheque,fashionucadeaukaart,fijncadeau,koffiecadeau,kokenzo,kookcadeau,nationaleentertainmentcard,naturesgift,podiumcadeaukaart,shoesaccessories,webshopgiftcard,wijncadeau,wonenzo,yourgift,vvvgiftcard,parfumcadeaukaart');

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
