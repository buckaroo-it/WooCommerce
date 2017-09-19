<?php
require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/buckaroopaypal/buckaroopaypal.php');

/**
* @package Buckaroo
*/
class WC_Gateway_Buckaroo_Paypal extends WC_Gateway_Buckaroo {
    
    function __construct() { 
        $woocommerce = getWooCommerceObject();
        $this->id = 'buckaroo_paypal';
        $this->title = 'PayPal';
        $this->icon         = apply_filters('woocommerce_buckaroo_paypal_icon', plugins_url('library/buckaroo_images/24x24/paypal.gif', __FILE__));
        $this->has_fields   = false;
        $this->method_title = "Buckaroo PayPal";
        $this->description = "Betaal met PayPal";
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $this->currency = BuckarooConfig::get('BUCKAROO_CURRENCY');
        $this->secretkey = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode = BuckarooConfig::getMode();
        $this->thumbprint = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture = BuckarooConfig::get('CULTURE');
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');
        
        $this->usenotification = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');
        // if (!isset($this->settings['usenotification'])) {
        //     $this->usenotification = 'FALSE';
        //     $this->notificationdelay = '0';
        // } else {
        //     $this->usenotification = $this->settings['usenotification'];
        //     $this->notificationdelay = $this->settings['notificationdelay'];
        // }
        
        parent::__construct();

        $this->supports           = array(
            'products',
            'refunds'
        );
        
        $this->notify_url = home_url('/');
        
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

        } else {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_api_wc_gateway_buckaroo_paypal', array( $this, 'response_handler' ) );
                $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Paypal', $this->notify_url);
        }
        //add_action( 'woocommerce_api_callback', 'response_handler' );           
    }

    /**
     * Can the order be refunded
     * @param object $order WC_Order
     * @return object & string
     */
    public function can_refund_order( $order ) {
        return $order && $order->get_transaction_id();
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );
        if ( ! $this->can_refund_order( $order ) ) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order = wc_get_order( $order_id );
        $paypal = new BuckarooPayPal();
        $paypal->amountDedit = 0;
        $paypal->amountCredit = $amount;
        $paypal->currency = $this->currency;
        $paypal->description = $reason;
        $paypal->orderId =  $order_id; // $order->get_order_number();
        $paypal->OriginalTransactionKey = $order->get_transaction_id();
        $paypal->returnUrl = $this->notify_url;
        $paypal->invoiceId = $order_id;
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $paypal->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response = null;
        try {
            $response = $paypal->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }
    
    /**
     * Validate fields
     * @return void;
     */
    public function validate_fields() { 
        resetOrder();
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
        $paypal = new BuckarooPayPal();
        if (method_exists($order, 'get_order_total')) {
                $paypal->amountDedit = $order->get_order_total();
        } else {
                $paypal->amountDedit = $order->get_total();
        }
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $paypal->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $paypal->currency = $this->currency;
        $paypal->description = $this->transactiondescription;
        $paypal->invoiceId =  getUniqInvoiceId($order_id);
        $paypal->orderId =   (string)$order_id;
        $paypal->returnUrl = $this->notify_url;
        $customVars = Array();
        $customVars['CustomerLastName'] = getWCOrderDetails($order_id, 'billing_last_name');

        if ($this->usenotification == 'TRUE') {
            $paypal->usenotification = 1;
            $customVars['Customergender'] = 0;
            $get_billing_email = getWCOrderDetails($order_id, 'billing_email');
            $customVars['Customeremail'] = !empty($get_billing_email) ? $get_billing_email : '';
            
            $get_billing_first_name = getWCOrderDetails($order_id, 'billing_first_name');
            $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['Notificationtype'] = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int)$this->notificationdelay . ' day'))));
        }

        if ($this->sellerprotection == 'TRUE') {
            $paypal->sellerprotection = 1;
            $get_shipping_postcode = getWCOrderDetails($order_id, 'shipping_postcode');
            $customVars['ShippingPostalCode'] = !empty($get_shipping_postcode) ? $get_shipping_postcode : '';

            $get_shipping_city = getWCOrderDetails($order_id, 'shipping_city');
            $customVars['ShippingCity'] = !empty($get_shipping_city) ? $get_shipping_city : '';

            $get_billing_address_1 = getWCOrderDetails($order_id, 'billing_address_1');
            $get_billing_address_2 = getWCOrderDetails($order_id, 'billing_address_2');
            $address_components = fn_buckaroo_get_address_components($get_billing_address_1." ".$get_billing_address_2);
            $customVars['ShippingStreet'] = !empty($address_components['street']) ? $address_components['street'] : '';
            $customVars['ShippingHouse'] = !empty($address_components['house_number']) ? $address_components['house_number'] : '';

            $customVars['StateOrProvince'] = getWCOrderDetails($order_id, 'billing_state');
            $customVars['Country'] = getWCOrderDetails($order_id, 'billing_country');

        }
        $response = $paypal->Pay($customVars);

        return fn_buckaroo_process_response($this, $response);
    }
    
     /**
     * Check response data
     * @access public
     */
    public function response_handler() {
        $woocommerce = getWooCommerceObject();
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $result = fn_buckaroo_process_response($this);
        if (!is_null($result))
           wp_safe_redirect($result['redirect']);
        else
            wp_safe_redirect($this->get_failed_url());
        exit;
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     * 
     * @access public
     */
    function init_form_fields() {

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

        $this->form_fields['sellerprotection'] = array(
            'title' => __( 'Seller Protection', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'select',
            'description' => __( 'Sends customer address information to PayPal to enable PayPal seller protection.', 'wc-buckaroo-bpe-gateway' ),
            'options' => array('TRUE'=>'Enabled', 'FALSE'=>'Disabled'),
            'default' => 'TRUE');
        //End Dynamic Rendering of Hidden Fields
    }
}