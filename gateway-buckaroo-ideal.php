<?php

require_once 'library/config.php';
require_once 'gateway-buckaroo.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/ideal/ideal.php');
class WC_Gateway_Buckaroo_Ideal extends WC_Gateway_Buckaroo {

    var $usenotification;
    var $notificationtype;
    var $notificationdelay;

    function __construct() {
        global $woocommerce;
        $this->id = 'buckaroo_ideal';
        $this->title = 'iDEAL';//$this->settings['title_ideal'];
        $this->icon 		= apply_filters('woocommerce_buckaroo_ideal_icon', plugins_url('library/buckaroo_images/24x24/ideal.png', __FILE__));
        $this->has_fields 	= true;
        $this->method_title = "Buckaroo iDEAL";
        $this->description = "Betaal met iDEAL";
        
        parent::__construct();
        if (!isset($this->settings['usenotification'])) {
            $this->usenotification = 'FALSE';
            $this->notificationdelay = '0';

        } else {
            $this->usenotification = $this->settings['usenotification'];
            $this->notificationdelay = $this->settings['notificationdelay'];
        }
        $this->supports           = array(
            'products',
            'refunds'
        );
        
        $this->notify_url = home_url('/');
        
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

        } else {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_api_wc_gateway_buckaroo_ideal', array( $this, 'response_handler' ) );
                $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Ideal', $this->notify_url);
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
        $ideal = new BuckarooIDeal();
        $ideal->amountDedit = 0;
        $ideal->amountCredit = $amount;
        $ideal->currency = $this->currency;
        $ideal->description = $reason;
        $ideal->invoiceId = $order_id;
        $ideal->orderId = $order_id;
        $ideal->OriginalTransactionKey = $order->get_transaction_id();
        $ideal->returnUrl = $this->notify_url;
        $response = null;
		try {				
			$response = $ideal->Refund();
		} catch (exception $e) {			
			update_post_meta($order_id, '_pushallowed', 'ok');
		}
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }
    
    /**
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
    public function validate_fields() { 
        if ( !isset( $_POST['buckaroo-ideal-issuer'] ) || !$_POST['buckaroo-ideal-issuer'] || empty($_POST['buckaroo-ideal-issuer']) ) {
            wc_add_notice( '<strong>iDEAL bank </strong> ' . __( 'is a required field.', 'woocommerce' ), 'error' );
        }
        resetOrder();
        return;
    }
    
    function process_payment($order_id) {
        global $woocommerce;
        // Validation: Required fields
        if ( !isset( $_POST['buckaroo-ideal-issuer'] ) || !$_POST['buckaroo-ideal-issuer'] || empty($_POST['buckaroo-ideal-issuer']) ) {
            wc_add_notice( '<strong>iDEAL bank </strong> ' . __( 'is a required field.', 'woocommerce' ), 'error' );
            return;
        }
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        if(WooV3Plus()){
           $order = wc_get_order($order_id);
        } else {
           $order = new WC_Order($order_id);
        }
        $ideal = new BuckarooIDeal();
        if (method_exists($order, 'get_order_total')) {
            $ideal->amountDedit = $order->get_order_total();
        } else {
            $ideal->amountDedit = $order->get_total();
        }
        $ideal->currency = $this->currency;
        $ideal->description = $this->transactiondescription;
        $ideal->invoiceId = (string)getUniqInvoiceId($order_id);
        $ideal->orderId = (string)$order_id;
        $ideal->issuer =  $_POST['buckaroo-ideal-issuer'];
        $ideal->returnUrl = $this->notify_url;
        $customVars = Array();
        if ($this->usenotification == 'TRUE') {
            $ideal->usenotification = 1;
            $customVars['Customergender'] = 0;
            if(WooV3Plus()){
                $get_billing_first_name = $order->get_billing_first_name();
                $get_billing_last_name = $order->get_billing_last_name();
                $get_billing_email = $order->get_billing_email();

                $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $order->get_billing_first_name() : '';
                $customVars['CustomerLastName'] = !empty($get_billing_last_name) ? $order->get_billing_last_name() : '';
                $customVars['CustomerEmail'] = !empty($get_billing_email) ? $order->get_billing_email() : '';
            }else{
                $customVars['CustomerFirstName'] = !empty($order->billing_first_name) ? $order->billing_first_name : '';
                $customVars['CustomerLastName'] = !empty($order->billing_last_name) ? $order->billing_last_name : '';
                $customVars['CustomerEmail'] = !empty($order->billing_email) ? $order->billing_email : '';
            }
            $customVars['Notificationtype'] = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + '. (int)$this->notificationdelay.' day'))));
        }
        $response = $ideal->Pay($customVars);            
        return fn_buckaroo_process_response($this, $response);
    }
    
    function payment_fields() {
    ?>
                    <?php if ($this->mode=='test') : ?><p><?php _e('TEST MODE', 'wc-buckaroo-bpe-gateway'); ?></p><?php endif; ?>
                    <?php if ($this->description) : ?><p><?php echo wpautop(wptexturize($this->description)); ?></p><?php endif; ?>
    <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('wc-buckaroo-bpe-gateway/library/css/ideal.css')?>">
    <fieldset>
            <p class="form-row form-row-wide">               
    <?php
        $first = true;
        foreach(BuckarooIDeal::getIssuerList() as $key => $issuer)
        {?>
             <div>
                 <input type='radio' value='<?php echo $key; ?>' name='buckaroo-ideal-issuer' id='buckaroo-ideal-issuer' style="display: inline-block !important;"/>
                 <div style="min-width: 100px; max-width: 100px; display: inline-block!important; vertical-align: top; "><?php echo _e($issuer["name"], 'wc-buckaroo-bpe-gateway')?></div>
                 <img src='<?php echo plugins_url('wc-buckaroo-bpe-gateway/library/buckaroo_images/ideal/' . $issuer["logo"], '', 'SSL')?>' style='display: inline-block !important; height: 15px; position: relative; top: -7px;'/>
             </div>
             <?php
             $first = false;
        }
        ?>
            </p>
    </fieldset>
                <?php



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