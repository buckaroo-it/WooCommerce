<?php
require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/transfer/transfer.php');

/**
* @package Buckaroo
*/
class WC_Gateway_Buckaroo_Transfer extends WC_Gateway_Buckaroo {
    var $datedue;
    var $sendemail;
    var $showpayproc;
    function __construct() { 
        $woocommerce = getWooCommerceObject();
        
        $this->id = 'buckaroo_transfer';
        $this->title = 'Bank Transfer';//$this->settings['title_paypal'];
        $this->icon = apply_filters('woocommerce_buckaroo_transfer_icon', plugins_url('library/buckaroo_images/24x24/transfer.jpg', __FILE__));
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Bank Transfer';
        $this->description  = "Betaal met Bank Transfer";
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $this->currency = get_woocommerce_currency();
        $this->secretkey = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode = BuckarooConfig::getMode();
        $this->thumbprint = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture = BuckarooConfig::get('CULTURE');
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');
        $this->usenotification = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');

        parent::__construct();

        $this->supports = array(
            'products',
            'refunds'
        );
        $this->datedue = $this->settings['datedue'];
        $this->sendemail = $this->settings['sendmail'];
        $this->showpayproc = ($this->settings['showpayproc'] == 'TRUE') ? true : false;
        $this->notify_url = home_url('/');
        
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

        } else {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_api_wc_gateway_buckaroo_transfer', array( $this, 'response_handler' ) );
                if ($this->showpayproc) add_action( 'woocommerce_thankyou_buckaroo_transfer' , array( $this, 'thankyou_description' ) );
                $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Transfer', $this->notify_url);
        }
        //add_action( 'woocommerce_api_callback', 'response_handler' );           
    }

    /**
     * Can the order be refunded
     * @param object $order WC_Order
     * @return object & string
     */
    public function can_refund_order($order) {
        return $order && $order->get_transaction_id();
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order( $order_id );
        if ( ! $this->can_refund_order( $order ) ) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order = wc_get_order( $order_id );
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }
        $transfer = new BuckarooTransfer();
        $transfer->amountDedit = 0;
        $transfer->amountCredit = $amount;
        $transfer->currency = $this->currency;
        $transfer->description = $reason;
        $transfer->invoiceId = $order_id;
        $transfer->orderId = $order_id;
        $transfer->OriginalTransactionKey = $order->get_transaction_id();
        $transfer->returnUrl = $this->notify_url;
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $transfer->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response = null;

        $orderDataForChecking = $transfer->getOrderRefundData();

        try {
            $transfer->checkRefundData($orderDataForChecking);
            $response = $transfer->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }
    
    /**
     * Validate fields
     * @return void;
     */
    public function validate_fields() { 
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
    function process_payment($order_id) {
        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
    
        $order = getWCOrder($order_id);
        $transfer = new BuckarooTransfer();
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }
        if (method_exists($order, 'get_order_total')) {
            $transfer->amountDedit = $order->get_order_total();
        } else {
            $transfer->amountDedit = $order->get_total();
        }
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $transfer->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $transfer->currency = $this->currency;
        $transfer->description = $this->transactiondescription;
        $transfer->invoiceId = (string)getUniqInvoiceId($order_id);
        $transfer->orderId = (string)$order_id;

        $customVars = array();

        $get_billing_first_name = getWCOrderDetails($order_id, 'billing_first_name');
        $get_billing_last_name = getWCOrderDetails($order_id, 'billing_last_name');
        $get_billing_email = getWCOrderDetails($order_id, 'billing_email');
        $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
        $customVars['CustomerLastName'] = !empty($get_billing_last_name) ? $get_billing_last_name : '';
        $customVars['Customeremail'] = !empty($get_billing_email) ? $get_billing_email : '';

        $customVars['SendMail'] = $this->sendemail; 
        if ((int) $this->datedue > -1) {
            $customVars['DateDue'] = date('Y-m-d', strtotime('now + ' . (int) $this->datedue . ' day'));
        } else {
            $customVars['DateDue'] = date('Y-m-d', strtotime('now + 14 day'));
        }
        $customVars['CustomerCountry'] = getWCOrderDetails($order_id, "billing_country");


        $transfer->returnUrl = $this->notify_url;


        if ($this->usenotification == 'TRUE') {
            $transfer->usenotification = 1;
            $customVars['Customergender'] = 0;

            $customVars['Notificationtype'] = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + '. (int)$this->notificationdelay.' day'))));
        }
        $response = $transfer->PayTransfer($customVars);
        return fn_buckaroo_process_response($this, $response);
    }
    
    /**
     * Check response data
     * 
     * @access public
     */
    public function response_handler() {
        $woocommerce = getWooCommerceObject();
        fn_buckaroo_process_response($this); 
        exit;
    }
    
    /**
     * Print thank you description to the screen.
     * 
     * @access public
     */
    public function thankyou_description() {
        if (!session_id()) @ session_start();
        print $_SESSION['buckaroo_response'];
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     * 
     * @access public
     */
    public function init_form_fields() {
        
        parent::init_form_fields();

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_certificate'));
        
        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_hide_local'));
        
        //Start Dynamic Rendering of Hidden Fields
        $options = get_option("woocommerce_".$this->id."_settings", null );
        $ccontent_arr = array();
        $keybase = 'certificatecontents';
        $keycount = 1;
        if (!empty($options["$keybase$keycount"])) {
            while(!empty($options["$keybase$keycount"])){
                $ccontent_arr[] = "$keybase$keycount";
                $keycount++;
            }
        }
        $while_key = 1;
        $selectcertificate_options = array('none' => 'None selected');
        while($while_key != $keycount) {
            $this->form_fields["certificatecontents$while_key"] = array(
                'title' => '',
                'type' => 'hidden', 
                'description' => '',
                'default' => ''
            );
            $this->form_fields["certificateuploadtime$while_key"] = array(
                'title' => '',
                'type' => 'hidden', 
                'description' => '',
                'default' => '');
            $this->form_fields["certificatename$while_key"] = array(
                'title' => '',
                'type' => 'hidden', 
                'description' => '',
                'default' => '');
            $selectcertificate_options["$while_key"] = $options["certificatename$while_key"];

            $while_key++;
        }
        $final_ccontent = $keycount;
        $this->form_fields["certificatecontents$final_ccontent"] = array(
            'title' => '',
            'type' => 'hidden', 
            'description' => '',
            'default' => '');
        $this->form_fields["certificateuploadtime$final_ccontent"] = array(
            'title' => '',
            'type' => 'hidden', 
            'description' => '',
            'default' => '');
        $this->form_fields["certificatename$final_ccontent"] = array(
            'title' => '',
            'type' => 'hidden', 
            'description' => '',
            'default' => '');
        
        $this->form_fields['selectcertificate'] = array(
            'title' => __('Select Certificate', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select', 
            'description' => __('Select your certificate by name.', 'wc-buckaroo-bpe-gateway'),
            'options' => $selectcertificate_options,
            'default' => 'none'
        );
        $this->form_fields['choosecertificate'] = array(
            'title' => __( '', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'file',
            'description' => __(''),
            'default' => '');




        $this->form_fields['datedue'] = array(
            'title' => __( 'Number of days till order expire', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'text', 
            'description' => __( 'Number of days to the date that the order should be payed.', 'wc-buckaroo-bpe-gateway' ),
            'default' => '14');
        $this->form_fields['sendmail'] = array(
            'title' => __( 'Send email', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'select', 
            'description' => __( 'Buckaroo sends an email to the customer with the payment procedures.', 'wc-buckaroo-bpe-gateway' ),
            'options' => array('TRUE'=>'Yes', 'FALSE'=>'No'),
            'default' => 'FALSE');
        $this->form_fields['showpayproc'] = array(
            'title' => __( 'Show payment procedures', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'select', 
            'description' => __( 'Show payment procedures on the thank you page after payment confirmation.', 'wc-buckaroo-bpe-gateway' ),
            'options' => array('TRUE'=>'Yes', 'FALSE'=>'No'),
            'default' => 'FALSE');
        $this->form_fields['usenotification'] = array(
            'title' => __( 'Use Notification Service', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'select',
            'description' => __( 'The notification service can be used to have the payment engine sent additional notifications.', 'wc-buckaroo-bpe-gateway' ),
            'options' => array('TRUE'=>'Yes', 'FALSE'=>'No'),
            'default' => 'FALSE');
        $this->form_fields['notificationdelay'] = array(
            'title' => __( 'Notification delay', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'text',
            'description' => __( 'The time at which the notification should be sent. If this is not specified, the notification is sent immediately.', 'wc-buckaroo-bpe-gateway' ),
            'default' => '0');
    } 
}
