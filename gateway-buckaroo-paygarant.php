<?php

require_once 'library/config.php';
require_once 'library/common.php';
require_once 'gateway-buckaroo.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/paygarant/paygarant.php');
class WC_Gateway_Buckaroo_PayGarant extends WC_Gateway_Buckaroo {
    var $datedue;
    var $sendemail;
    var $paymentmethodspg;
    var $showpayproc;
    function __construct() { 
        global $woocommerce;
        
        $this->id = 'buckaroo_paygarant';
        $this->title = 'Payment Guarantee';
        $this->icon 		= apply_filters('woocommerce_buckaroo_paypal_icon', plugins_url('library/buckaroo_images/24x24/transfergarant.png', __FILE__));
        $this->has_fields 	= true;
        $this->method_title = 'Buckaroo Payment Guarantee';
        $this->description = "Betaal met Payment Guarantee";
        
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
        
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

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
        $paygarant = new BuckarooPayGarant();
        $paygarant->amountDedit = 0;
        $paygarant->amountCredit = $amount;
        $paygarant->currency = $this->currency;
        $paygarant->description = $reason;
        $paygarant->invoiceId = $order_id;
        $paygarant->orderId = $order_id;
        $paygarant->OriginalTransactionKey = $order->get_transaction_id();
        $paygarant->returnUrl = $this->notify_url;
        $response = null;
        try {
            $response = $paygarant->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }
    
    function process_payment($order_id) {
            global $woocommerce;

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
        
            $order = new WC_Order( $order_id );
            $paygarant = new BuckarooPayGarant();
            if (method_exists($order, 'get_order_total')) {
                $paygarant->amountDedit = $order->get_order_total();
            } else {
                $paygarant->amountDedit = $order->get_total();
            }
            $paygarant->currency = $this->currency;
            $paygarant->description = $this->transactiondescription;
            $paygarant->invoiceId = (string)getUniqInvoiceId($order_id);
            $paygarant->orderId = (string)$order_id;

            $customVars = array();
            $customVars['CustomerCode'] = $order->customer_user;
            $customVars['CustomerFirstName'] = $_POST['buckaroo-paygarant-firstname'];
            $customVars['CustomerLastName'] = $_POST['buckaroo-paygarant-lastname'];
            $customVars['CustomerInitials'] = $this->getInitials($_POST['buckaroo-paygarant-firstname']);
            $customVars['CustomerBirthDate'] = date('Y-m-d', strtotime($birthdate)); //1983-09-28
            $customVars['CustomerGender'] = $_POST['buckaroo-paygarant-gender'];
            $customVars['CustomerEmail'] = $_POST['buckaroo-paygarant-email'];
            
            $number = $this->cleanup_phone($order->billing_phone);;
            if ($number['type'] == 'mobile') {
                $customVars['MobilePhoneNumber'] = $number['phone'];
            } else {
                $customVars['PhoneNumber'] = $number['phone'];
            }
            $customVars['CustomerAccountNumber'] = $_POST['buckaroo-paygarant-bankaccount'];
            
            $address_components = fn_buckaroo_get_address_components($order->billing_address_1." ".$order->billing_address_2);
            $customVars['ADDRESS'][0]['AddressType'] = 'INVOICE';
            $customVars['ADDRESS'][0]['ZipCode'] = $order->billing_postcode;
            $customVars['ADDRESS'][0]['City'] = $order->billing_city;
            if (!empty($address_components['street']))
                $customVars['ADDRESS'][0]['Street'] = $address_components['street'];
            if (!empty($address_components['house_number']))
                $customVars['ADDRESS'][0]['HouseNumber'] = $address_components['house_number'];
            if (!empty($address_components['number_addition']))
                $customVars['ADDRESS'][0]['HouseNumberSuffix'] = $address_components['number_addition'];
            $customVars['ADDRESS'][0]['Country'] = $order->billing_country;

            $address_components2 = fn_buckaroo_get_address_components($order->shipping_address_1." ".$order->shipping_address_2);
            $customVars['ADDRESS'][1]['AddressType'] = 'SHIPPING';
            $customVars['ADDRESS'][1]['ZipCode'] = $order->shipping_postcode;
            $customVars['ADDRESS'][1]['City'] = $order->shipping_city;
            if (!empty($address_components2['street']))
                $customVars['ADDRESS'][1]['Street'] = $address_components2['street'];
            if (!empty($address_components2['house_number']))
                $customVars['ADDRESS'][1]['HouseNumber'] = $address_components2['house_number'];
            if (!empty($address_components2['number_addition']))
                $customVars['ADDRESS'][1]['HouseNumberSuffix'] = $address_components2['number_addition'];
            $customVars['ADDRESS'][1]['Country'] = $order->shipping_country;

            $customVars['SendMail'] = $this->sendemail;
            if ((int) $this->datedue > -1)
                $customVars['InvoiceDate'] = date('Y-m-d', strtotime('now + ' . (int) $this->datedue . ' day'));
            else
                $customVars['InvoiceDate'] = date('Y-m-d', strtotime('now + 14 day'));

            $customVars['DateDue'] = date('Y-m-d', strtotime($customVars['InvoiceDate'].' + 14 day'));
            $customVars['AmountVat'] = $order->order_tax;
            if (!empty($this->paymentmethodspg)) {
                $customVars['PaymentMethodsAllowed'] = implode(",", $this->paymentmethodspg);
            }
           
            $paygarant->returnUrl = $this->notify_url;
            if ($this->usenotification == 'TRUE') {
                $paygarant->usenotification = 1;
                $customVars['Customergender'] = 0;
                $customVars['CustomerFirstName'] = !empty($order->billing_first_name) ? $order->billing_first_name : '';
                $customVars['CustomerLastName'] = !empty($order->billing_last_name) ? $order->billing_last_name : '';
                $customVars['Customeremail'] = !empty($order->billing_email) ? $order->billing_email : '';
                $customVars['Notificationtype'] = 'PaymentComplete';
                $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + '. (int)$this->notificationdelay.' day'))));
            }
            $response = $paygarant->PaymentInvitation($customVars);
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
        
        function payment_fields() {
            ?>
                <?php if ($this->mode=='test') : ?><p><?php _e('TEST MODE', 'wc-buckaroo-bpe-gateway'); ?></p><?php endif; ?>
                <?php if ($this->description) : ?><p><?php echo wpautop(wptexturize($this->description)); ?></p><?php endif; ?>

                <fieldset>
                    <p class="form-row">
                            <label for="buckaroo-paygarant-gender"><?php echo _e('Gender:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                            <input id="buckaroo-paygarant-genderm" name="buckaroo-paygarant-gender" class="" type="radio" value="1" checked="checked" /> Male &nbsp;
                            <input id="buckaroo-paygarant-genderf" name="buckaroo-paygarant-gender" class="" type="radio" value="2"/> Female
                    </p>
                    <p class="form-row form-row-wide validate-required">
                            <label for="buckaroo-paygarant-firstname"><?php echo _e('Firstname:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                            <input id="buckaroo-paygarant-firstname" name="buckaroo-paygarant-firstname" class="input-text" type="text" maxlength="250" value="<?php echo get_user_meta( $GLOBALS["current_user"]->ID, 'billing_first_name', true );?>" />
                    </p>
                    <p class="form-row form-row-wide validate-required">
                            <label for="buckaroo-paygarant-lastname"><?php echo _e('Lastname:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                            <input id="buckaroo-paygarant-lastname" name="buckaroo-paygarant-lastname" class="input-text" type="text" maxlength="250" value="<?php echo get_user_meta( $GLOBALS["current_user"]->ID, 'billing_last_name', true );?>" />
                    </p>
                    <p class="form-row form-row-wide validate-required">
                            <label for="buckaroo-paygarant-bankaccount"><?php echo _e('IBAN:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                            <input id="buckaroo-paygarant-bankaccount" name="buckaroo-paygarant-bankaccount" class="input-text" type="text" maxlength="25" autocomplete="off" value="" />
                    </p>
                    <p class="form-row form-row-wide validate-required validate-email">
                            <label for="buckaroo-paygarant-email"><?php echo _e('E-mail:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                            <input id="buckaroo-paygarant-email" name="buckaroo-paygarant-email" class="input-text" type="text" maxlength="512" value="<?php echo get_user_meta( $GLOBALS["current_user"]->ID, 'billing_email', true );?>" />
                    </p>
                    <p class="form-row form-row-wide validate-required">
                            <label for="buckaroo-paygarant-birthdate"><?php echo _e('Birthdate:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                            <input id="buckaroo-paygarant-birthdate" name="buckaroo-paygarant-birthdate" class="input-text" type="text" maxlength="10" value="" placeholder="YYYY-MM-DD" />
                    </p>
                </fieldset>
            <?php
         }      

        function init_form_fields() {

            parent::init_form_fields();
            $this->form_fields['datedue'] = array(
                                'title' => __( 'Number of days between order and invoice', 'wc-buckaroo-bpe-gateway' ),
                                'type' => 'text', 
                                'description' => __( 'Maximum days of delay 30.', 'wc-buckaroo-bpe-gateway' ),
                                'default' => '14');
            $this->form_fields['sendmail'] = array(
                                'title' => __( 'Send Email', 'wc-buckaroo-bpe-gateway' ),
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