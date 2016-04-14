<?php

require_once 'library/config.php';
require_once 'library/common.php';
require_once 'gateway-buckaroo.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/sepadirectdebit/sepadirectdebit.php');
class WC_Gateway_Buckaroo_SepaDirectDebit extends WC_Gateway_Buckaroo {
    var $usecreditmanagment;
    var $datedue;
    var $maxreminderlevel;
    var $paymentmethodssdd;
    var $showpayproc;
    function __construct() { 
        global $woocommerce;
        
        $this->id = 'buckaroo_sepadirectdebit';
        $this->title = 'SEPA Direct Debit';//$this->settings['title_paypal'];
        $this->icon 		= apply_filters('woocommerce_buckaroo_paypal_icon', plugins_url('library/buckaroo_images/24x24/directdebit.png', __FILE__));
        $this->has_fields 	= false;
        $this->method_title = 'Buckaroo SEPA Direct Debit';
        $this->description = "Betaal met SEPA Direct Debit";
        
        parent::__construct();

        $this->supports           = array(
            'products',
            'refunds'
        );
        $this->usecreditmanagment = $this->settings['usecreditmanagment'];
        $this->invoicedelay = $this->settings['invoicedelay'];

        if (!isset($this->settings['usenotification'])) {
            $this->usenotification = 'FALSE';
            $this->notificationdelay = 0;
            $this->notificationtype = 'PaymentComplete';
        } else {
            $this->usenotification = $this->settings['usenotification'];
            $this->notificationdelay = $this->settings['notificationdelay'];
            $this->notificationtype = $this->settings['notificationtype'];
        }

        $this->datedue = $this->settings['datedue'];
        $this->maxreminderlevel = $this->settings['maxreminderlevel'];
        $this->paymentmethodssdd = '';
        if (!empty($this->settings['paymentmethodssdd'])) {
            $this->paymentmethodssdd = $this->settings['paymentmethodssdd'];
        }
        $this->notify_url = home_url('/');
        
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

        } else {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_api_wc_gateway_buckaroo_sepadirectdebit', array( $this, 'response_handler' ) );
                if ($this->showpayproc) add_action( 'woocommerce_thankyou_buckaroo_sepadirectdebit' , array( $this, 'thankyou_description' ) );
                $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_SepaDirectDebit', $this->notify_url);
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
        $sepadirectdebit = new BuckarooSepaDirectDebit();
        $sepadirectdebit->amountDedit = 0;
        $sepadirectdebit->amountCredit = $amount;
        $sepadirectdebit->currency = $this->currency;
        $sepadirectdebit->description = $reason;
        $sepadirectdebit->invoiceId = $order_id;
        if ($this->mode=='test') {
            $sepadirectdebit->invoiceId = 'WP_'.(string)$order_id;
        }
        $sepadirectdebit->orderId = $order_id;
        $sepadirectdebit->OriginalTransactionKey = $order->get_transaction_id();
        $sepadirectdebit->returnUrl = $this->notify_url;
        $response = null;
        try {
            $response = $sepadirectdebit->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }
    
    function process_payment($order_id) {
            global $woocommerce;
          
            $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';

            if (empty($_POST['buckaroo-sepadirectdebit-accountname'])
              ||empty($_POST['buckaroo-sepadirectdebit-iban']))
            {
                wc_add_notice( __("Please fill in all required fields", 'wc-buckaroo-bpe-gateway'), 'error' );
                return; //array("result" => "failure","messages"=>"Please fill in all required fields","refresh"=>"false");
            };
            
            $sepadirectdebit = new BuckarooSepaDirectDebit();
            if (!$sepadirectdebit->isIBAN($_POST['buckaroo-sepadirectdebit-iban'])){
                wc_add_notice( __("Wrong IBAN number", 'wc-buckaroo-bpe-gateway'), 'error' );
                return;// array('result' 	=> 'error');
            }
            
            $order = new WC_Order( $order_id );
            if (method_exists($order, 'get_order_total')) {
                $sepadirectdebit->amountDedit = $order->get_order_total();
            } else {
                $sepadirectdebit->amountDedit = $order->get_total();
            }
            $sepadirectdebit->currency = $this->currency;
            $sepadirectdebit->description = $this->transactiondescription;
            $sepadirectdebit->customeraccountname = $_POST['buckaroo-sepadirectdebit-accountname'];
            $sepadirectdebit->CustomerBIC = $_POST['buckaroo-sepadirectdebit-bic'];
            $sepadirectdebit->CustomerIBAN = $_POST['buckaroo-sepadirectdebit-iban'];
            $sepadirectdebit->invoiceId = getUniqInvoiceId((string)$order_id, $this->mode);
            $sepadirectdebit->orderId = (string)$order_id;

            $customVars = array();
            if ($this->usecreditmanagment == 'TRUE')
            {
                $birthdate = $_POST['buckaroo-sepadirectdebit-birthdate'];
                if (!$this->validateDate($birthdate,'Y-m-d')){
                    wc_add_notice( __("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error' );
                    return;
                }
                $sepadirectdebit->usecreditmanagment = 1;
                $customVars['MaxReminderLevel'] = $this->maxreminderlevel;
                $customVars['CustomerCode'] = $order->customer_user;
            
                $customVars['CompanyName'] = !empty($order->billing_company) ? $order->billing_company : '';
                $customVars['CustomerFirstName'] = !empty($order->billing_first_name) ? $order->billing_first_name : '';
                $customVars['CustomerLastName'] = !empty($order->billing_last_name) ? $order->billing_last_name : '';
                $customVars['CustomerInitials'] = $this->getInitials($order->billing_first_name);
                $customVars['Customergender'] = $_POST['buckaroo-sepadirectdebit-gender'];
                $customVars['CustomerBirthDate'] = date('Y-m-d', strtotime($birthdate)); //1983-09-28
                $customVars['Customeremail'] = !empty($order->billing_email) ? $order->billing_email : '';
                $number = $this->cleanup_phone($order->billing_phone);
                if ($number['type'] == 'mobile') {
                    $customVars['MobilePhoneNumber'] = $number['phone'];
                } else {
                    $customVars['PhoneNumber'] = $number['phone'];
                }
                $customVars['InvoiceDate'] = date('Y-m-d', strtotime('now + ' . (int) $this->invoicedelay . ' day'));
                $customVars['DateDue'] = date('Y-m-d', strtotime($customVars['InvoiceDate'].' + '. (int)$this->datedue.' day'));

                $address_components = fn_buckaroo_get_address_components($order->billing_address_1." ".$order->billing_address_2);
                $customVars['ADDRESS']['ZipCode'] = $order->billing_postcode;
                $customVars['ADDRESS']['City'] = $order->billing_city;
                if (!empty($address_components['street']))
                    $customVars['ADDRESS']['Street'] = $address_components['street'];
                if (!empty($address_components['house_number']))
                    $customVars['ADDRESS']['HouseNumber'] = $address_components['house_number'];
                if (!empty($address_components['number_addition']))
                    $customVars['ADDRESS']['HouseNumberSuffix'] = $address_components['number_addition'];
                $customVars['ADDRESS']['Country'] = $order->billing_country;
            
                $customVars['AmountVat'] = $order->order_tax;

                if (!empty($this->paymentmethodssdd)) {
                    $customVars['PaymentMethodsAllowed'] = implode(",", $this->paymentmethodssdd);
                }

            }
            if ($this->usenotification == 'TRUE') {
				if ($this->usecreditmanagment != 'TRUE')
				{
					$this->invoicedelay = 0;
				}
                $sepadirectdebit->usenotification = 1;
                $customVars['Customergender'] = $_POST['buckaroo-sepadirectdebit-gender'];
                $customVars['CustomerFirstName'] = !empty($order->billing_first_name) ? $order->billing_first_name : '';
                $customVars['CustomerLastName'] = !empty($order->billing_last_name) ? $order->billing_last_name : '';
                $customVars['Customeremail'] = !empty($order->billing_email) ? $order->billing_email : '';
                $customVars['Notificationtype'] = $this->notificationtype;
                $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int) $this->invoicedelay . ' day')).' + '. (int)$this->notificationdelay.' day'));
            }
            $sepadirectdebit->returnUrl = $this->notify_url;
            $response = $sepadirectdebit->PayDirectDebit($customVars);
            return fn_buckaroo_process_response($this, $response, $this->mode);
            
            /*return array(
                    'result' 	=> 'error',
                    //'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );*/
    }
    
    function payment_fields() {
        $accountname = get_user_meta( $GLOBALS["current_user"]->ID, 'billing_first_name', true )." ".get_user_meta( $GLOBALS["current_user"]->ID, 'billing_last_name', true );
        ?>
            <?php if ($this->mode=='test') : ?><p><?php _e('TEST MODE', 'wc-buckaroo-bpe-gateway'); ?></p><?php endif; ?>
            <?php if ($this->description) : ?><p><?php echo wpautop(wptexturize($this->description)); ?></p><?php endif; ?>
            
            <fieldset>
                <?php if($this->usecreditmanagment == 'TRUE'):?>
                <p class="form-row">
                        <label for="buckaroo-sepadirectdebit-gender"><?php echo _e('Gender:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                        <input id="buckaroo-sepadirectdebit-genderm" name="buckaroo-sepadirectdebit-gender" class="" type="radio" value="1" checked /> <?php echo _e('Male', 'wc-buckaroo-bpe-gateway')?> &nbsp;
                        <input id="buckaroo-sepadirectdebit-genderf" name="buckaroo-sepadirectdebit-gender" class="" type="radio" value="2"/> <?php echo _e('Female', 'wc-buckaroo-bpe-gateway')?>
                </p>
                <p class="form-row form-row-wide validate-required">
                        <label for="buckaroo-sepadirectdebit-birthdate"><?php echo _e('Birthdate:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                        <input id="buckaroo-sepadirectdebit-birthdate" name="buckaroo-sepadirectdebit-birthdate" class="input-text" type="text" maxlength="250" autocomplete="off" value="" placeholder="YYYY-MM-DD" />
                </p>
                <?php endif;?>
                <?php if($this->usenotification == 'TRUE' && $this->usecreditmanagment == 'FALSE'):?>
                    <p class="form-row">
                        <label for="buckaroo-sepadirectdebit-gender"><?php echo _e('Gender:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                        <input id="buckaroo-sepadirectdebit-genderm" name="buckaroo-sepadirectdebit-gender" class="" type="radio" value="1" checked /> <?php echo _e('Male', 'wc-buckaroo-bpe-gateway')?> &nbsp;
                        <input id="buckaroo-sepadirectdebit-genderf" name="buckaroo-sepadirectdebit-gender" class="" type="radio" value="2"/> <?php echo _e('Female', 'wc-buckaroo-bpe-gateway')?>
                    </p>
                <?php endif;?>
                <p class="form-row form-row-wide validate-required">
                        <label for="buckaroo-sepadirectdebit-accountname"><?php echo _e('Bank account holder:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                        <input id="buckaroo-sepadirectdebit-accountname" name="buckaroo-sepadirectdebit-accountname" class="input-text" type="text" maxlength="250" autocomplete="off" value="<?php echo $accountname;?>" />
                </p>
                <p class="form-row form-row-wide validate-required">
                    <label for="buckaroo-sepadirectdebit-iban"><?php echo _e('IBAN:', 'wc-buckaroo-bpe-gateway')?><span class="required">*</span></label>
                    <input id="buckaroo-sepadirectdebit-iban" name="buckaroo-sepadirectdebit-iban" class="input-text" type="text" maxlength="25" autocomplete="off" value="" />
                </p>
                <p class="form-row form-row-wide">
                        <label for="buckaroo-sepadirectdebit-bic"><?php echo _e('BIC:', 'wc-buckaroo-bpe-gateway')?></label>
                        <input id="buckaroo-sepadirectdebit-bic" name="buckaroo-sepadirectdebit-bic" class="input-text" type="text" maxlength="11" autocomplete="off" value="" />
                </p>
            </fieldset>
        <?php
     }     
            /**
	 * Check response data
	 */
    
	public function response_handler() {
		global $woocommerce;
                fn_buckaroo_process_response($this); 
                exit;
        }

        function init_form_fields() {

            parent::init_form_fields();
            $this->form_fields['usecreditmanagment'] = array(
                                'title' => __( 'Use Credit Managment', 'wc-buckaroo-bpe-gateway' ),
                                'type' => 'select', 
                                'description' => __( 'Buckaroo sends payment reminders to the customer. (Contact Buckaroo before activating Credit Management. By default this is excluded in the contract.)', 'wc-buckaroo-bpe-gateway' ),
                                'options' => array('TRUE'=>'Yes', 'FALSE'=>'No'),
                                'default' => 'FALSE');
            $this->form_fields['invoicedelay'] = array(
                                'title' => __( 'Invoice delay (in days)', 'wc-buckaroo-bpe-gateway' ),
                                'type' => 'text', 
                                'description' => __( 'Specify the amount of days before Buckaroo invoices the order and sends out the payment mail.', 'wc-buckaroo-bpe-gateway' ),
                                'default' => '3');
            $this->form_fields['datedue'] = array(
                                'title' => __( 'Due date (in days)', 'wc-buckaroo-bpe-gateway' ),
                                'type' => 'text', 
                                'description' => __( 'Specify the number of days the customer has to complete their payment before the first reminder e-mail will be sent by Buckaroo.', 'wc-buckaroo-bpe-gateway' ),
                                'default' => '14');           
            $this->form_fields['maxreminderlevel'] = array(
                                'title' => __( 'Max reminder level', 'wc-buckaroo-bpe-gateway' ),
                                'type' => 'select', 
                                'description' => __( 'Select the maximum reminder level buckaroo will use.', 'wc-buckaroo-bpe-gateway' ),
                                'options' => array('4'=>'4', '3'=>'3', '2'=>'2', '1'=>'1'),
                                'default' => '4');
            $this->form_fields['paymentmethodssdd'] = array(
                                'title' => __( 'Allowed payment methods', 'wc-buckaroo-bpe-gateway' ),
                                'type' => 'multiselect',
                                'css' => 'height: 650px;',
                                'description' => __( 'Select allowed payment methods for SEPA Direct Debit. (Ctrl+Click select multiple)', 'wc-buckaroo-bpe-gateway' ),
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

			$this->form_fields['notificationtype'] = array(
				'title' => __( 'Notification Type', 'wc-buckaroo-bpe-gateway' ),
				'type' => 'select',
				'description' => __( 'PreNotification: A pre-notification that is sent some time before performing a scheduled action. PaymentComplete: A notification that is sent when a transaction has been completed with success.', 'wc-buckaroo-bpe-gateway' ),
				'options' => array('PreNotification'=>'Pre Notification', 'PaymentComplete'=>'Payment Complete'),
				'default' => 'FALSE');

            $this->form_fields['notificationdelay'] = array(
                'title' => __( 'Notification delay', 'wc-buckaroo-bpe-gateway' ),
                'type' => 'text',
                'description' => __( 'The time at which the notification should be sent. If this is not specified, the notification is sent immediately.', 'wc-buckaroo-bpe-gateway' ),
                'default' => '0');
        } 
}