<?php

require_once 'library/config.php';
require_once 'library/common.php';
require_once 'gateway-buckaroo.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/transfer/transfer.php');
class WC_Gateway_Buckaroo_Transfer extends WC_Gateway_Buckaroo {
    var $datedue;
    var $sendemail;
    var $showpayproc;
    function __construct() { 
        global $woocommerce;
        
        $this->id = 'buckaroo_transfer';
        $this->title = 'Bank Transfer';//$this->settings['title_paypal'];
        $this->icon 		= apply_filters('woocommerce_buckaroo_paypal_icon', plugins_url('library/buckaroo_images/24x24/transfer.jpg', __FILE__));
        $this->has_fields 	= false;
        $this->method_title = 'Buckaroo Bank Transfer';
        $this->description = "Betaal met Bank Transfer";
        
        parent::__construct();

        $this->supports           = array(
            'products',
            'refunds'
        );
        $this->datedue = $this->settings['datedue'];
        $this->sendemail = $this->settings['sendmail'];
        $this->showpayproc = ($this->settings['showpayproc'] == 'TRUE')?true:false;
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
        $transfer = new BuckarooTransfer();
        $transfer->amountDedit = 0;
        $transfer->amountCredit = $amount;
        $transfer->currency = $this->currency;
        $transfer->description = $reason;
        $transfer->invoiceId = $order_id;
        $transfer->orderId = $order_id;
        $transfer->OriginalTransactionKey = $order->get_transaction_id();
        $transfer->returnUrl = $this->notify_url;
        $response = null;
        try {
            $response = $transfer->Refund();
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
        
            $order = new WC_Order( $order_id );
            $transfer = new BuckarooTransfer();
            if (method_exists($order, 'get_order_total')) {
                $transfer->amountDedit = $order->get_order_total();
            } else {
                $transfer->amountDedit = $order->get_total();
            }
            $transfer->currency = $this->currency;
            $transfer->description = $this->transactiondescription;
            $transfer->invoiceId = (string)getUniqInvoiceId($order_id);
            $transfer->orderId = (string)$order_id;

            $customVars = array();
            $customVars['CustomerEmail'] = $order->billing_email;       
            $customVars['CustomerFirstName'] = $order->billing_first_name;
            $customVars['CustomerLastName'] = $order->billing_last_name; 
            $customVars['SendMail'] = $this->sendemail; 
            if ((int) $this->datedue > -1)
                $customVars['DateDue'] = date('Y-m-d', strtotime('now + ' . (int) $this->datedue . ' day'));
            else
                $customVars['DateDue'] = date('Y-m-d', strtotime('now + 14 day'));;
            $customVars['CustomerCountry'] = $order->billing_country;

            $transfer->returnUrl = $this->notify_url;


            if ($this->usenotification == 'TRUE') {
                $transfer->usenotification = 1;
                $customVars['Customergender'] = 0;
                $customVars['CustomerFirstName'] = !empty($order->billing_first_name) ? $order->billing_first_name : '';
                $customVars['CustomerLastName'] = !empty($order->billing_last_name) ? $order->billing_last_name : '';
                $customVars['Customeremail'] = !empty($order->billing_email) ? $order->billing_email : '';
                $customVars['Notificationtype'] = 'PaymentComplete';
                $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + '. (int)$this->notificationdelay.' day'))));
            }
            $response = $transfer->PayTransfer($customVars);
            return fn_buckaroo_process_response($this, $response);
    }
    
     /**
	 * Check response data
	 */
    
	public function response_handler() {
		global $woocommerce;
                fn_buckaroo_process_response($this); 
                exit;
        }
        
        public function  thankyou_description(){
              if ( ! session_id() ) @ session_start();
              print $_SESSION['buckaroo_response'];
        }

        function init_form_fields() {

            parent::init_form_fields();
            $this->form_fields['datedue'] = array(
                                'title' => __( 'Number of days till order expire', 'wc-buckaroo-bpe-gateway' ),
                                'type' => 'text', 
                                'description' => __( 'Number of days to the date that the order should be payed.', 'wc-buckaroo-bpe-gateway' ),
                                'default' => '14');
            $this->form_fields['sendmail'] = array(
                                'title' => __( 'Send Email', 'wc-buckaroo-bpe-gateway' ),
                                'type' => 'select', 
                                'description' => __( 'Buckaroo sends an email to the customer with the payment procedures.', 'wc-buckaroo-bpe-gateway' ),
                                'options' => array('TRUE'=>'Yes', 'FALSE'=>'No'),
                                'default' => 'FALSE');
            $this->form_fields['showpayproc'] = array(
                                'title' => __( 'Show payment procedures', 'wc-buckaroo-bpe-gateway' ),
                                'type' => 'select', 
                                'description' => __( 'Show payment procedures on Thank You page after payment confirmation.', 'wc-buckaroo-bpe-gateway' ),
                                'options' => array('TRUE'=>'Yes', 'FALSE'=>'No'),
                                'default' => 'FALSE');

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