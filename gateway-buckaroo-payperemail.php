<?php
require_once 'library/include.php';
require_once dirname(__FILE__) . '/library/api/paymentmethods/payperemail/payperemail.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_PayPerEmail extends WC_Gateway_Buckaroo
{
    public $paymentmethodppe;
    public function __construct()
    {
        $woocommerce                  = getWooCommerceObject();
        $this->id                     = 'buckaroo_payperemail';
        $this->icon = apply_filters('woocommerce_buckaroo_payperemail_icon', BuckarooConfig::getIconPath('payperemail.png', 'new/PayPerEmail.png'));
        $this->title                  = 'PayPerEmail';
        $this->has_fields             = true;
        $this->method_title           = "Buckaroo PayPerEmail";
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

        $this->supports = array(
            'products',
        );

        $this->paymentmethodppe = '';
        if (!empty($this->settings['paymentmethodppe'])) {
            $this->paymentmethodppe = $this->settings['paymentmethodppe'];
        }
        $this->frontendVisible = $this->settings['show_PayPerEmail_frontend'] ?? '';

        $this->notify_url = home_url('/');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_buckaroo_payperemail', array($this, 'response_handler'));
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_PayPerEmail', $this->notify_url);
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
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = wc_get_order($order_id);

        $payperemail                         = new BuckarooPayPerEmail();
        $payperemail->amountDedit            = 0;
        $payperemail->amountCredit           = $amount;
        $payperemail->currency               = $this->currency;
        $payperemail->description            = $reason;
        $payperemail->invoiceId              = $order->get_order_number();
        $payperemail->orderId                = $order_id;
        $payperemail->OriginalTransactionKey = $order->get_transaction_id();
        $payperemail->returnUrl              = $this->notify_url;
        $payment_type                        = str_replace('buckaroo_', '', strtolower($this->id));
        $payperemail->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                            = null;

        $orderDataForChecking = $payperemail->getOrderRefundData();

        try {
            $payperemail->checkRefundData($orderDataForChecking);
            $response = $payperemail->Refund();
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
        if ($this->isVisibleOnFrontend()) {
            if (empty($_POST['buckaroo-payperemail-gender'])) {
                wc_add_notice(__("Please select gender", 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if (empty($_POST['buckaroo-payperemail-firstname'])) {
                wc_add_notice(__("Please enter firstname", 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if (empty($_POST['buckaroo-payperemail-lastname'])) {
                wc_add_notice(__("Please enter lastname", 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if (empty($_POST['buckaroo-payperemail-email'])) {
                wc_add_notice(__("Please enter email", 'wc-buckaroo-bpe-gateway'), 'error');
            } elseif (!is_email($_POST['buckaroo-payperemail-email'])) {
                wc_add_notice(__("Please enter valid email", 'wc-buckaroo-bpe-gateway'), 'error');
            }
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
    public function process_payment($order_id, $paylink = false)
    {
        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = getWCOrder($order_id);
        $payperemail          = new BuckarooPayPerEmail();

        if (method_exists($order, 'get_order_total')) {
            $payperemail->amountDedit = $order->get_order_total();
        } else {
            $payperemail->amountDedit = $order->get_total();
        }
        $payment_type                 = str_replace('buckaroo_', '', strtolower($this->id));
        $payperemail->channel         = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $payperemail->currency        = $this->currency;
        $payperemail->description     = $this->transactiondescription;
        $payperemail->invoiceId       = (string) getUniqInvoiceId($order->get_order_number());
        $payperemail->orderId         = (string) $order_id;
        $payperemail->returnUrl       = $this->notify_url;
        $customVars                   = array();
        $customVars['CustomerGender'] = 0;
        $get_billing_first_name       = getWCOrderDetails($order_id, 'billing_first_name');
        $get_billing_last_name        = getWCOrderDetails($order_id, 'billing_last_name');
        $get_billing_email            = getWCOrderDetails($order_id, 'billing_email');

        $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
        $customVars['CustomerLastName']  = !empty($get_billing_last_name) ? $get_billing_last_name : '';
        $customVars['Customeremail']     = !empty($get_billing_email) ? $get_billing_email : '';

        if ($this->isVisibleOnFrontend() && !is_admin()) {
            $customVars['CustomerGender']    = $_POST['buckaroo-payperemail-gender'];
            $customVars['CustomerFirstName'] = $_POST['buckaroo-payperemail-firstname'];
            $customVars['CustomerLastName']  = $_POST['buckaroo-payperemail-lastname'];
            $customVars['Customeremail']     = $_POST['buckaroo-payperemail-email'];
        }

        if (!empty($this->paymentmethodppe)) {
            $customVars['PaymentMethodsAllowed'] = implode(",", $this->paymentmethodppe);
        }

        if ($paylink) {
            $customVars['merchantSendsEmail'] = 'true';
        }

        if (!empty($this->settings['expirationDate'])) {
            $customVars['ExpirationDate'] = date('Y-m-d', time() + $this->settings['expirationDate'] * 86400);
        }

        $response = $payperemail->PaymentInvitation($customVars);
        return fn_buckaroo_process_response($this, $response);
    }

    public function isVisibleOnFrontend()
    {
        if (!empty($this->frontendVisible) && strtolower($this->frontendVisible) === "yes") {
            return true;
        }

        return false;
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        $accountname = get_user_meta($GLOBALS["current_user"]->ID, 'billing_first_name', true) . " " . get_user_meta($GLOBALS["current_user"]->ID, 'billing_last_name', true);
        $post_data   = array();

        $customerId        = get_current_user_id();
        $customerFirstName = '';
        $customerLastName  = '';
        $customerEmail     = '';
        if (!empty($customerId)) {
            $customerFirstName = get_user_meta($customerId, 'billing_first_name', true);
            $customerLastName  = get_user_meta($customerId, 'billing_last_name', true);
            $customerEmail     = get_user_meta($customerId, 'billing_email', true);
        }

        if (!empty($_POST["post_data"])) {
            parse_str($_POST["post_data"], $post_data);
        }
        ?>
        <?php if ($this->mode == 'test'): ?><p><?php _e('TEST MODE', 'wc-buckaroo-bpe-gateway');?></p><?php endif;?>
        <?php if ($this->description): ?><p><?php echo wpautop(wptexturize($this->description)); ?></p><?php endif;?>

        <fieldset>

            <p class="form-row">
                <label for="buckaroo-payperemail-gender"><?php echo _e('Gender:', 'wc-buckaroo-bpe-gateway') ?><span
                            class="required">*</span></label>
                <input id="buckaroo-payperemail-genderm" name="buckaroo-payperemail-gender" class="" type="radio"
                       value="1" checked
                       style="float:none; display: inline !important;"/> <?php echo _e('Male', 'wc-buckaroo-bpe-gateway') ?>
                &nbsp;
                <input id="buckaroo-payperemail-genderf" name="buckaroo-payperemail-gender" class="" type="radio"
                       value="2"
                       style="float:none; display: inline !important;"/> <?php echo _e('Female', 'wc-buckaroo-bpe-gateway') ?>
            </p>

            <p class="form-row validate-required">
                <label for="buckaroo-payperemail-firstname"><?php echo _e('First Name:', 'wc-buckaroo-bpe-gateway') ?>
                    <span class="required">*</span></label>
                <input id="buckaroo-payperemail-firstname" name="buckaroo-payperemail-firstname" class="input-text"
                       type="text" autocomplete="off" value="<?php echo $customerFirstName ?? '' ?>">
            </p>

            <p class="form-row validate-required">
                <label for="buckaroo-payperemail-lastname"><?php echo _e('Last Name:', 'wc-buckaroo-bpe-gateway') ?>
                    <span class="required">*</span></label>
                <input id="buckaroo-payperemail-lastname" name="buckaroo-payperemail-lastname" class="input-text"
                       type="text" autocomplete="off" value="<?php echo $customerLastName ?? '' ?>">
            </p>

            <p class="form-row validate-required">
                <label for="buckaroo-payperemail-email"><?php echo _e('Email:', 'wc-buckaroo-bpe-gateway') ?><span
                            class="required">*</span></label>
                <input id="buckaroo-payperemail-email" name="buckaroo-payperemail-email"
                       type="email" autocomplete="off" value="<?php echo $customerEmail ?? '' ?>">
            </p>

            <p class="required" style="float:right;">* <?php echo _e('Required', 'wc-buckaroo-bpe-gateway') ?></p>
        </fieldset>
        <?php
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

        $this->form_fields['show_PayPerEmail_frontend'] = array(
            'title'       => __('Show on Checkout page', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'checkbox',
            'description' => __('Show PayPerEnail on Checkout page', 'wc-buckaroo-bpe-gateway'),
            'default'     => 'no',
        );

        $this->form_fields['show_PayLink'] = array(
            'title'       => __('Show PayLink', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Show PayLink in admin order actions', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'TRUE',
        );

        $this->form_fields['show_PayPerEmail'] = array(
            'title'       => __('Show PayPerEmail', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Show PayPerEmail in admin order actions', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'TRUE',
        );

        $this->form_fields['expirationDate'] = array(
            'title'       => __('Due date (in days)', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('The expiration date for the paylink.', 'wc-buckaroo-bpe-gateway'),
            'default'     => '');

        $this->form_fields['paymentmethodppe'] = array(
            'title'       => __('Allowed methods', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'multiselect',
            'options'     => array(
                'amex'               => 'American Express',
                'cartebancaire'      => 'Carte Bancaire',
                'cartebleuevisa'     => 'Carte Bleue',
                'dankort'            => 'Dankort',
                'mastercard'         => 'Mastercard',
                'postepay'           => 'PostePay',
                'visa'               => 'Visa',
                'visaelectron'       => 'Visa Electron',
                'vpay'               => 'Vpay',
                'maestro'            => "Maestro",
                'bancontactmrcash'   => 'Bancontact / Mr Cash',
                'transfer'           => 'Bank Transfer',
                'giftcard'           => 'Giftcards',
                'giropay'            => 'Giropay',
                'ideal'              => 'iDEAL',
                'paypal'             => 'PayPal',
                'sepadirectdebit'    => 'SEPA Direct Debit',
                'sofortueberweisung' => 'Sofort Banking',
                'belfius'            => 'Belfiusg',
                'Przelewy24'         => 'P24',
                'RequestToPay'       => 'Request To Pay',
            ),
            'description' => __('select which methods will be appear to customer', 'wc-buckaroo-bpe-gateway'),
            'default'     => array('amex', 'cartebancaire', 'cartebleuevisa', 'dankort', 'mastercard', 'postepay', 'visa', 'visaelectron', 'vpay', 'maestro', 'bancontactmrcash', 'transfer', 'giftcard', 'giropay', 'ideal', 'paypal', 'sepadirectdebit', 'sofortueberweisung', 'belfius', 'Przelewy24', 'RequestToPay'),
        );
    }

}
