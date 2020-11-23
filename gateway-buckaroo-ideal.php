<?php
require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/ideal/ideal.php');

/**
* @package Buckaroo
*/
class WC_Gateway_Buckaroo_Ideal extends WC_Gateway_Buckaroo {

    var $usenotification;
    var $notificationtype;
    var $notificationdelay;

    function __construct() {
        $woocommerce = getWooCommerceObject();
            // return false;   
        $this->id = 'buckaroo_ideal';
        $this->title = 'iDEAL';//$this->settings['title_ideal'];
        $this->icon         = apply_filters('woocommerce_buckaroo_ideal_icon', plugins_url('library/buckaroo_images/ideal.png', __FILE__));
        $this->has_fields   = true;
        $this->method_title = "Buckaroo iDEAL";
        $this->description = "Betaal met iDEAL";
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $this->currency = get_woocommerce_currency();
        $this->secretkey = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode = BuckarooConfig::getMode();
        $this->thumbprint = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture = BuckarooConfig::get('CULTURE');
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');
        $this->usenotification = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');
        // if(!checkCurrencySupported($this->id) && !is_admin()){ 
        //     unset($this->id);
        //     unset($this->title);
        // }
        
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
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }
        $ideal = new BuckarooIDeal();
        $ideal->amountDedit = 0;
        $ideal->amountCredit = $amount;
        $ideal->currency = $this->currency;
        $ideal->description = $reason;
        $ideal->invoiceId = $order_id;
        $ideal->orderId = $order_id;
        $ideal->OriginalTransactionKey = $order->get_transaction_id();
        $ideal->returnUrl = $this->notify_url;
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $ideal->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response = null;

        $orderDataForChecking = $ideal->getOrderRefundData();

		try {
		    $ideal->checkRefundData($orderDataForChecking);
		    $response = $ideal->Refund();
		} catch (exception $e) {			
			update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
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
            wc_add_notice( __("<strong>iDEAL bank </strong> is a required field.", 'wc-buckaroo-bpe-gateway'), 'error' );
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
    function process_payment($order_id) {
        $woocommerce = getWooCommerceObject();
        // Validation: Required fields
        if ( !isset( $_POST['buckaroo-ideal-issuer'] ) || !$_POST['buckaroo-ideal-issuer'] || empty($_POST['buckaroo-ideal-issuer']) ) {
            wc_add_notice( __("<strong>iDEAL bank </strong> is a required field.", 'wc-buckaroo-bpe-gateway'), 'error' );
            return;
        }
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order = getWCOrder($order_id);
        $ideal = new BuckarooIDeal();
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }
        if (method_exists($order, 'get_order_total')) {
            $ideal->amountDedit = $order->get_order_total();
        } else {
            $ideal->amountDedit = $order->get_total();
        }
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $ideal->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
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

            $get_billing_first_name = getWCOrderDetails($order_id, 'billing_first_name');
            $get_billing_last_name = getWCOrderDetails($order_id, 'billing_last_name');
            $get_billing_email = getWCOrderDetails($order_id, 'billing_email');
            $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['CustomerLastName'] = !empty($get_billing_last_name) ? $get_billing_last_name : '';
            $customVars['Customeremail'] = !empty($get_billing_email) ? $get_billing_email : '';
            $customVars['Notificationtype'] = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + '. (int)$this->notificationdelay.' day'))));
        }
        $response = $ideal->Pay($customVars);            
        return fn_buckaroo_process_response($this, $response);
    }
    
    /**
     * Payment form on checkout page
     */
    function payment_fields() { ?>

        <?php if ($this->mode=='test') : ?>
            <p>
                <?php _e('TEST MODE', 'wc-buckaroo-bpe-gateway'); ?>
            </p>
        <?php endif; ?>

        <?php if ($this->description) : ?>
            <p>
                <?php echo wpautop(wptexturize($this->description)); ?>
            </p>
        <?php endif; ?>

        <fieldset style="background: none">
            <p class="form-row form-row-wide">
                <select name='buckaroo-ideal-issuer' id='buckaroo-ideal-issuer'>
                    <?php $first = true; ?>
                    <option value='0'  style='color: grey !important'>
                        <?php echo __('Select your bank', 'wc-buckaroo-bpe-gateway')?>
                    </option>
                    <?php foreach(BuckarooIDeal::getIssuerList() as $key => $issuer) : ?>
                        <div>
                            <option value='<?php echo $key; ?>'>
                                <?php echo _e($issuer["name"], 'wc-buckaroo-bpe-gateway')?>
                            </option>
                        </div>
                        <?php $first = false; ?>
                    <?php endforeach ?>
                </select>
            </p>
        </fieldset>

    <?php } //Here ends the function.

    /**
     * Check response data
     * 
     * @access public
     */
    public function response_handler() {
        $woocommerce = getWooCommerceObject();
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $result = fn_buckaroo_process_response($this);
        if (!is_null($result)){
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
