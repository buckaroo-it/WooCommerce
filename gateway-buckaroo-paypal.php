<?php
require_once 'library/common.php';
require_once 'library/config.php';
require_once 'gateway-buckaroo.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/buckaroopaypal/buckaroopaypal.php');
class WC_Gateway_Buckaroo_Paypal extends WC_Gateway_Buckaroo {
    
    function __construct() { 
        global $woocommerce;
        $this->id = 'buckaroo_paypal';
        $this->title = 'PayPal';
        $this->icon 		= apply_filters('woocommerce_buckaroo_paypal_icon', plugins_url('library/buckaroo_images/24x24/paypal.gif', __FILE__));
        $this->has_fields 	= false;
        $this->method_title = "Buckaroo PayPal";
        $this->description = "Betaal met PayPal";
        
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
     * @param  WC_Order $order
     * @return bool
     */
    public function can_refund_order( $order ) {
        return $order && $order->get_transaction_id();
    }

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

        $response = null;
        try {
            $response = $paypal->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }
    
    public function validate_fields() { 
        resetOrder();
        return;
    }
    
    function process_payment($order_id) {
        global $woocommerce;

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        // $order = 
        if(WooV3Plus()){
           $order = wc_get_order($order_id);
        } else {
           $order = new WC_Order($order_id);
        }
        $paypal = new BuckarooPayPal();
        if (method_exists($order, 'get_order_total')) {
                $paypal->amountDedit = $order->get_order_total();
        } else {
                $paypal->amountDedit = $order->get_total();
        }
        $paypal->currency = $this->currency;
        $paypal->description = $this->transactiondescription;
        $paypal->invoiceId =  getUniqInvoiceId($order_id);
        $paypal->orderId =   (string)$order_id;
        $paypal->returnUrl = $this->notify_url;
        $customVars = Array();
        if(WooV3Plus()) {
            $get_billing_last_name = $order->get_billing_last_name();
            $customVars['CustomerLastName'] = ($get_billing_last_name != NULL) ? $order->get_billing_last_name() : '';
        } else {
            $customVars['CustomerLastName'] = !empty($order->billing_last_name) ? $order->billing_last_name : '';
        }

        if ($this->usenotification == 'TRUE') {
            $paypal->usenotification = 1;
            $customVars['Customergender'] = 0;
            if (WooV3Plus()) {
                $get_billing_email = $order->get_billing_email();
                $customVars['CustomerEmail'] = !empty($get_billing_email) ? $order->get_billing_email() : '';
                $get_billing_first_name = $order->get_billing_first_name();
                $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $order->get_billing_first_name() : '';
            } else {
                $customVars['CustomerEmail'] = !empty($order->billing_email) ? $order->billing_email : '';
                $customVars['CustomerFirstName'] = !empty($order->billing_first_name) ? $order->billing_first_name : '';
            }
            $customVars['Notificationtype'] = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int)$this->notificationdelay . ' day'))));
        }
        if ($this->sellerprotection == 'TRUE'){
            $paypal->sellerprotection = 1;
            if (WooV3Plus()) { 
                $get_shipping_postcode = $order->get_shipping_postcode();
                $get_shipping_city = $order->get_shipping_city();
                $get_billing_address_1 = $order->get_billing_address_1();
                $get_billing_address_2 = $order->get_billing_address_2();

                $customVars['ShippingPostalCode'] = !empty($get_shipping_postcode) ? $order->get_shipping_postcode() : '';
                $customVars['ShippingCity'] = !empty($get_shipping_city) ? $order->get_shipping_city() : '';
                $address_components = fn_buckaroo_get_address_components($get_billing_address_1." ".$get_billing_address_2);
               
                $customVars['ShippingStreet'] = !empty($address_components['street']) ? $address_components['street'] : '';
                $customVars['ShippingHouse'] = !empty($address_components['house_number']) ? $address_components['house_number'] : '';

                $get_billing_state = $order->get_billing_state();
                $get_billing_country = $order->get_billing_country();

                $customVars['StateOrProvince'] = !empty($get_billing_state) ? $order->get_billing_state() : '';
                $customVars['Country'] = !empty($get_billing_country) ? $order->get_billing_country() : '';
            } else{
                $customVars['ShippingPostalCode'] = !empty($order->shipping_postcode) ? $order->shipping_postcode : '';
                $customVars['ShippingCity'] = !empty($order->shipping_city) ? $order->shipping_city : '';
                $address_components = fn_buckaroo_get_address_components($order->billing_address_1." ".$order->billing_address_2);
                $customVars['ShippingStreet'] = !empty($address_components['street']) ? $address_components['street'] : '';
                $customVars['ShippingHouse'] = !empty($address_components['house_number']) ? $address_components['house_number'] : '';
                $customVars['StateOrProvince'] = !empty($order->billing_state) ? $order->billing_state : '';
                $customVars['Country'] = !empty($order->billing_country) ? $order->billing_country : '';
            }
        }
        $response = $paypal->Pay($customVars);

        return fn_buckaroo_process_response($this, $response);
    }
    
     /**
	 * Check response data
	 */
    
	public function response_handler() {
		global $woocommerce;
                $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
                $result = fn_buckaroo_process_response($this);
                if (!is_null($result))
                   wp_safe_redirect($result['redirect']);
                else
                    wp_safe_redirect($this->get_failed_url());
                exit;
        }

    function init_form_fields() {

        parent::init_form_fields();

        $this->form_fields['usenotification'] = array(
            'title' => __( 'Use Notification Service', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'select',
            'description' => __( 'The notification service can be used to have the payment engine sent additional notifications at certain points. Different type of notifications can be sent and also using different methods to sent them.)', 'wc-buckaroo-bpe-gateway' ),
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
    }

}