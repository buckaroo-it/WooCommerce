<?php
require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/paygarant/paygarant.php');

/**
* @package Buckaroo
*/
class WC_Gateway_Buckaroo_PayGarant extends WC_Gateway_Buckaroo {
    var $datedue;
    var $sendemail;
    var $paymentmethodspg;
    var $showpayproc;
    function __construct() {
        $woocommerce = getWooCommerceObject();
        
        $this->id = 'buckaroo_paygarant';
        $this->title = 'Payment Guarantee';
        $this->icon = apply_filters('woocommerce_buckaroo_paygarant_icon', plugins_url('library/buckaroo_images/24x24/transfergarant.png', __FILE__));
        $this->has_fields = true;
        $this->method_title = 'Buckaroo Payment Guarantee';
        $this->description = "Betaal met Payment Guarantee";
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $this->currency = BuckarooConfig::get('BUCKAROO_CURRENCY');
        $this->secretkey = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode = BuckarooConfig::getMode();
        $this->thumbprint = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture = BuckarooConfig::get('CULTURE');
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');
        $this->usenotification = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');

        parent::__construct();

        $this->supports           = array(
            'products',
            'refunds'
        );
        $this->datedue = $this->settings['datedue'];
        $this->sendemail = $this->settings['sendmail'];
        $this->paymentmethodspg = '';
        if (!empty($this->settings['paymentmethodspg'])) {
            $this->paymentmethodspg = $this->settings['paymentmethodspg'];
        }
        $this->notify_url = home_url('/');
        
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '<')) {

        } else {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_wc_gateway_buckaroo_paygarant', array( $this, 'response_handler' ) );
            if ($this->showpayproc) add_action( 'woocommerce_thankyou_buckaroo_paygarant' , array( $this, 'thankyou_description' ) );
            $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_PayGarant', $this->notify_url);
        }
        //add_action( 'woocommerce_api_callback', 'response_handler' );           
    }

    /**
     * Can the order be refunded
     * @access public
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
        $order = getWCOrder($order_id);
        if ( ! $this->can_refund_order( $order ) ) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order = wc_get_order( $order_id );
        $paygarant = new BuckarooPayGarant();
        $paygarant->amountDedit = 0;
        $paygarant->amountCredit = $amount;
        $paygarant->currency = $this->currency;
        $paygarant->description = $reason;
        $paygarant->invoiceId = $order_id;
        $paygarant->orderId = $order_id;
        $paygarant->OriginalTransactionKey = $order->get_transaction_id();
        $paygarant->returnUrl = $this->notify_url;
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $paygarant->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response = null;
        try {
            $response = $paygarant->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }
    
    /**
     * Validate payment fields on the frontend.
     * 
     * @access public
     * @return void
     */
    public function validate_fields() {
        if (empty($_POST['buckaroo-paygarant-firstname'])
            ||empty($_POST['buckaroo-paygarant-lastname'])
            ||empty($_POST['buckaroo-paygarant-email'])
            ||empty($_POST['buckaroo-paygarant-bankaccount'])
            ||empty($_POST['buckaroo-paygarant-birthdate'])) {
              wc_add_notice( __("Please fill in all required fields", 'wc-buckaroo-bpe-gateway'), 'error' );
        }
        $birthdate = $_POST['buckaroo-paygarant-birthdate'];
        if (!$this->validateDate($birthdate,'Y-m-d')){
            wc_add_notice( __("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error' );
        }
        resetOrder();
        return;
    }
    
    /**
     * Process payment
     * 
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    function process_payment($order_id) {
        $woocommerce = getWooCommerceObject();

        if (empty($_POST['buckaroo-paygarant-firstname'])
          ||empty($_POST['buckaroo-paygarant-lastname'])
          ||empty($_POST['buckaroo-paygarant-email'])
          ||empty($_POST['buckaroo-paygarant-bankaccount'])
          ||empty($_POST['buckaroo-paygarant-birthdate']))
        {
            wc_add_notice( __("Please fill in all required fields", 'wc-buckaroo-bpe-gateway'), 'error' );
            return; //array("result" => "failure","messages"=>"Please fill in all required fields","refresh"=>"false");
        };
        
        $birthdate = $_POST['buckaroo-paygarant-birthdate'];
        if (!$this->validateDate($birthdate,'Y-m-d')){
            wc_add_notice( __("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error' );
            return;
        }
        
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
    
        $order = getWCOrder($order_id);
        $paygarant = new BuckarooPayGarant();
        if (method_exists($order, 'get_order_total')) {
            $paygarant->amountDedit = $order->get_order_total();
        } else {
            $paygarant->amountDedit = $order->get_total();
        }
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $paygarant->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $paygarant->currency = $this->currency;
        $paygarant->description = $this->transactiondescription;
        $paygarant->invoiceId = (string)getUniqInvoiceId($order_id);
        $paygarant->orderId = (string)$order_id;

        $customVars = array();
        //[9Yards][JW]
        // Debug Log throws error "customer_user was called incorrectly. Order properties should not be accessed directly.. This message was added in version 3.0."
        // get_customer_id() seems to return the same property as customer_user.
        $customVars['CustomerCode'] = (WooV3Plus()) ? $order->get_customer_id() : $order->customer_user ;
        $customVars['CustomerFirstName'] = $_POST['buckaroo-paygarant-firstname'];
        $customVars['CustomerLastName'] = $_POST['buckaroo-paygarant-lastname'];
        $customVars['CustomerInitials'] = $this->getInitials($_POST['buckaroo-paygarant-firstname']);
        $customVars['CustomerBirthDate'] = date('Y-m-d', strtotime($birthdate)); //1983-09-28
        $customVars['CustomerGender'] = $_POST['buckaroo-paygarant-gender'];
        $customVars['Customeremail'] = $_POST['buckaroo-paygarant-email'];
        $get_billing_phone = getWCOrderDetails($order_id, 'billing_phone');
        $number = $this->cleanup_phone($get_billing_phone);
        if ($number['type'] == 'mobile') {
            $customVars['MobilePhoneNumber'] = $number['phone'];
        } else {
            $customVars['PhoneNumber'] = $number['phone'];
        }
        $customVars['CustomerAccountNumber'] = $_POST['buckaroo-paygarant-bankaccount'];
        //Billing Address
        $get_billing_address_1 = getWCOrderDetails($order_id, 'billing_address_1');
        $get_billing_address_2 = getWCOrderDetails($order_id, 'billing_address_2');
        $address_components1 = fn_buckaroo_get_address_components($get_billing_address_1." ".$get_billing_address_2);
        $customVars['ADDRESS'][0]['AddressType'] = 'INVOICE';
        $customVars['ADDRESS'][0]['ZipCode'] = getWCOrderDetails($order_id, 'billing_postcode');
        $customVars['ADDRESS'][0]['City'] = getWCOrderDetails($order_id, 'billing_city');
        if (!empty($address_components1['street'])) {
            $customVars['ADDRESS'][0]['Street'] = $address_components1['street'];
        }
        if (!empty($address_components1['house_number'])) {
            $customVars['ADDRESS'][0]['HouseNumber'] = $address_components1['house_number'];
        }
        if (!empty($address_components1['number_addition'])) {
            $customVars['ADDRESS'][0]['HouseNumberSuffix'] = $address_components1['number_addition'];
        }
        $customVars['ADDRESS'][0]['Country'] = getWCOrderDetails($order_id, 'billing_country');
        //Shipping Address
        $get_shipping_address_1 = getWCOrderDetails($order_id, 'shipping_address_1');
        $get_shipping_address_2 = getWCOrderDetails($order_id, 'shipping_address_2');
        $address_components2 = fn_buckaroo_get_address_components($get_shipping_address_1." ".$get_shipping_address_2);
        $customVars['ADDRESS'][1]['AddressType'] = 'SHIPPING';

        $customVars['ADDRESS'][1]['ZipCode'] = getWCOrderDetails($order_id, 'shipping_postcode');
        $customVars['ADDRESS'][1]['City'] = getWCOrderDetails($order_id, 'shipping_city');
        if (!empty($address_components2['street'])) {
            $customVars['ADDRESS'][1]['Street'] = $address_components2['street'];
        }
        if (!empty($address_components2['house_number'])) {
            $customVars['ADDRESS'][1]['HouseNumber'] = $address_components2['house_number'];
        }
        if (!empty($address_components2['number_addition'])) {
            $customVars['ADDRESS'][1]['HouseNumberSuffix'] = $address_components2['number_addition'];
        }
        $customVars['ADDRESS'][1]['Country'] = getWCOrderDetails($order_id, 'shipping_country');
       

        $customVars['SendMail'] = $this->sendemail;
        if ((int) $this->datedue > -1)
            $customVars['InvoiceDate'] = date('Y-m-d', strtotime('now + ' . (int) $this->datedue . ' day'));
        else
            $customVars['InvoiceDate'] = date('Y-m-d', strtotime('now + 14 day'));

        $customVars['DateDue'] = date('Y-m-d', strtotime($customVars['InvoiceDate'].' + 14 day'));
        $customVars['AmountVat'] = (WooV3Plus()) ? $order->get_total_tax(): $order->order_tax;
        if (!empty($this->paymentmethodspg)) {
            $customVars['PaymentMethodsAllowed'] = implode(",", $this->paymentmethodspg);
        }
       
        $paygarant->returnUrl = $this->notify_url;
        if ($this->usenotification == 'TRUE') {
            $paygarant->usenotification = 1;
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
        $response = $paygarant->PaymentInvitation($customVars);
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
     * Payment form on checkout page
     */
    function payment_fields() { ?>
        <?php if ($this->mode=='test') : ?><p><?php _e('TEST MODE', 'wc-buckaroo-bpe-gateway'); ?></p><?php endif; ?>
        <?php if ($this->description) : ?><p><?php echo wpautop(wptexturize($this->description)); ?></p><?php endif; ?>

        <fieldset>
            <p class="form-row">
                <label for="buckaroo-paygarant-gender">
                    <?php echo _e('Gender:', 'wc-buckaroo-bpe-gateway')?>
                    <span class="required">*</span>
                </label>
                <input id="buckaroo-paygarant-genderm" name="buckaroo-paygarant-gender" class="" type="radio" value="1" checked="checked"  style="float:none;" /> <?php echo _e('Male', 'wc-buckaroo-bpe-gateway')?> &nbsp;
                <input id="buckaroo-paygarant-genderf" name="buckaroo-paygarant-gender" class="" type="radio" value="2" style="float:none;" /> <?php echo _e('Female', 'wc-buckaroo-bpe-gateway')?>
            </p>
            <p class="form-row form-row-wide validate-required">
                <label for="buckaroo-paygarant-firstname">
                    <?php echo _e('Firstname:', 'wc-buckaroo-bpe-gateway')?>
                    <span class="required">*</span>
                </label>
                <input id="buckaroo-paygarant-firstname" name="buckaroo-paygarant-firstname" class="input-text" type="text" maxlength="250" value="<?php echo get_user_meta( $GLOBALS["current_user"]->ID, 'billing_first_name', true );?>" />
            </p>
            <p class="form-row form-row-wide validate-required">
                <label for="buckaroo-paygarant-lastname">
                    <?php echo _e('Lastname:', 'wc-buckaroo-bpe-gateway')?>
                    <span class="required">*</span>
                </label>
                <input id="buckaroo-paygarant-lastname" name="buckaroo-paygarant-lastname" class="input-text" type="text" maxlength="250" value="<?php echo get_user_meta( $GLOBALS["current_user"]->ID, 'billing_last_name', true );?>" />
            </p>
            <p class="form-row form-row-wide validate-required">
                <label for="buckaroo-paygarant-bankaccount">
                    <?php echo _e('IBAN:', 'wc-buckaroo-bpe-gateway')?>
                    <span class="required">*</span>
                </label>
                <input id="buckaroo-paygarant-bankaccount" name="buckaroo-paygarant-bankaccount" class="input-text" type="text" maxlength="25" autocomplete="off" value="" />
            </p>
            <p class="form-row form-row-wide validate-required validate-email">
                <label for="buckaroo-paygarant-email">
                    <?php echo _e('E-mail:', 'wc-buckaroo-bpe-gateway')?>
                    <span class="required">*</span>
                </label>
                <input id="buckaroo-paygarant-email" name="buckaroo-paygarant-email" class="input-text" type="text" maxlength="512" value="<?php echo get_user_meta( $GLOBALS["current_user"]->ID, 'billing_email', true );?>" />
            </p>
            <p class="form-row form-row-wide validate-required">
                <label for="buckaroo-paygarant-birthdate">
                    <?php echo _e('Birthdate:', 'wc-buckaroo-bpe-gateway')?>
                    <span class="required">*</span>
                </label>
                <input id="buckaroo-paygarant-birthdate" name="buckaroo-paygarant-birthdate" class="input-text" type="text" maxlength="10" value="" placeholder="YYYY-MM-DD" />
            </p>
            <p class="required" style="float:right;">* Verplicht</p>
        </fieldset>
    <?php }

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
            'title' => __( 'Number of days between order and invoice', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'text', 
            'description' => __( 'Maximum days of delay 30.', 'wc-buckaroo-bpe-gateway' ),
            'default' => '14');
        $this->form_fields['sendmail'] = array(
            'title' => __( 'Send email', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'select', 
            'description' => __( 'Send Buckaroo Payment Plaza e-mail to customer.', 'wc-buckaroo-bpe-gateway' ),
            'options' => array('TRUE'=>'Yes', 'FALSE'=>'No'),
            'default' => 'FALSE');
        $this->form_fields['paymentmethodspg'] = array(
            'title' => __( 'Allowed payment methods', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'multiselect',
            'css' => 'height: 650px;',
            'description' => __( 'Select allowed payment methods for Payment Guarantee. (Ctrl+Click select multiple)', 'wc-buckaroo-bpe-gateway' ),
            'options' => array(
                'ideal' => 'iDEAL',
                'transfer' => 'Overboeking (SEPA Credit Transfer)',
                'mastercard' => 'Mastercard',
                'visa' => 'Visa',
                'maestro' => 'eMaestro',
                'giropay' => 'Giropay',
                'paypal' => 'Paypal',
                'bancontactmrcash' => 'Mr. Cash/Bancontact',
                'sepadirectdebit' => 'Machtiging (SEPA Direct Debit)',
                'sofortueberweisung' => 'Sofortbanking',
                'paymentguarantee' => 'Payment guarantee',
                'paysafecard' => 'Paysafecard',
                'empayment' => 'èM! Payment',
                'babygiftcard' => 'Baby Giftcard',
                'babyparkgiftcard' => 'Babypark Giftcard',
                'beautywellness' => 'Beauty Wellness',
                'boekenbon' => 'Boekenbon',
                'boekenvoordeel' => 'Boekenvoordeel',
                'designshopsgiftcard' => 'Designshops Giftcard',
                'fijncadeau' => 'Fijn Cadeau',
                'koffiecadeau' => 'Koffie Cadeau',
                'kokenzo' => 'Koken En Zo',
                'kookcadeau' => 'Kook Cadeau',
                'nationaleentertainmentcard' => 'Nationale Entertainment Card',
                'naturesgift' => 'Natures Gift',
                'podiumcadeaukaart' => 'Podium Cadeaukaart',
                'shoesaccessories' => 'Shoes Accessories',
                'webshopgiftcard' => 'Webshop Giftcard',
                'wijncadeau' => 'Wijn Cadeau',
                'wonenzo' => 'Wonen En Zo',
                'yourgift' => 'Your Gift',
                'fashioncheque' => 'Fashioncheque',
            ));

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