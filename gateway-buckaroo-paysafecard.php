<?php

require_once 'library/config.php';
require_once 'gateway-buckaroo.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/paysafecard/paysafecard.php');
class WC_Gateway_Buckaroo_Paysafecard extends WC_Gateway_Buckaroo {
    
    function __construct() { 
        global $woocommerce;
        $this->id = 'buckaroo_paysafecard';
        $this->title = 'Paysafecard';
        $this->icon 		= apply_filters('woocommerce_buckaroo_paysafecard_icon', plugins_url('library/buckaroo_images/24x24/paysafe.png', __FILE__));
        $this->has_fields 	= false;
        $this->method_title = "Buckaroo Paysafecard";
        $this->description = "Betaal met Paysafecard";
        
        parent::__construct();

        $this->supports           = array(
            'products',
            'refunds'
        );
        
        $this->notify_url = home_url('/');
        
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

        } else {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_api_wc_gateway_buckaroo_paysafecard', array( $this, 'response_handler' ) );
                $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Paysafecard', $this->notify_url);
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
        $paysafecard = new BuckarooPaySafeCard();
        $paysafecard->amountDedit = 0;
        $paysafecard->amountCredit = $amount;
        $paysafecard->currency = $this->currency;
        $paysafecard->description = $reason;
        $paysafecard->invoiceId = $order->get_order_number();
        $paysafecard->orderId = $order_id;
        $paysafecard->OriginalTransactionKey = $order->get_transaction_id();
        $paysafecard->returnUrl = $this->notify_url;
        $response = null;
        try {
            $response = $paysafecard->Refund();
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
            
            if(WooV3Plus()){
               $order = wc_get_order($order_id);
            } else {
               $order = new WC_Order($order_id);
            }
            $paysafecard = new BuckarooPaySafeCard();
            if (method_exists($order, 'get_order_total')) {
                $paysafecard->amountDedit = $order->get_order_total();
            } else {
                $paysafecard->amountDedit = $order->get_total();
            }
            $paysafecard->currency = $this->currency;
            $paysafecard->description = $this->transactiondescription;
            $paysafecard->invoiceId = (string)getUniqInvoiceId($order_id);
            $paysafecard->orderId = (string)$order_id;
            $paysafecard->returnUrl = $this->notify_url;
            $customVars = Array();
            if ($this->usenotification == 'TRUE') {
                $paysafecard->usenotification = 1;
                $customVars['Customergender'] = 0;
                if(WooV3Plus()){
                    $get_billing_first_name = $order->get_billing_first_name();
                    $get_billing_last_name = $order->get_billing_last_name();
                    $get_billing_email = $order->get_billing_email();

                    $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $order->get_billing_first_name() : '';
                    $customVars['CustomerLastName'] = !empty($get_billing_last_name) ? $order->get_billing_last_name() : '';
                    $customVars['CustomerEmail'] = !empty($get_billing_email) ? $order->get_billing_email() : '';
                } else {
                    $customVars['CustomerFirstName'] = !empty($order->billing_first_name) ? $order->billing_first_name : '';
                    $customVars['CustomerLastName'] = !empty($order->billing_last_name) ? $order->billing_last_name : '';
                    $customVars['CustomerEmail'] = !empty($order->billing_email) ? $order->billing_email : '';
                }
                $customVars['Notificationtype'] = 'PaymentComplete';
                $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + '. (int)$this->notificationdelay.' day'))));
            }
            $response = $paysafecard->Pay($customVars);
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
    }

}