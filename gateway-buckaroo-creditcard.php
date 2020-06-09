<?php
require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/creditcard/creditcard.php');

/**
* @package Buckaroo
*/
class WC_Gateway_Buckaroo_Creditcard extends WC_Gateway_Buckaroo {
    var $creditCardProvider = [];
    function __construct() {
        $woocommerce = getWooCommerceObject();
        $this->id = 'buckaroo_creditcard';
        $this->title = 'Creditcards';
        $this->icon         = apply_filters('woocommerce_buckaroo_creditcard_icon', plugins_url('library/buckaroo_images/24x24/cc.gif', __FILE__));
        $this->has_fields   = false;
        $this->method_title = "Buckaroo Creditcards";
        $this->description = "Betaal met Creditcards";
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

        if (isset($this->settings['AllowedProvider'])) {
            $this->creditCardProvider = $this->settings['AllowedProvider'];
        } else {
            $this->creditCardProvider = [];
        }

        $this->creditcardmethod = (isset($this->settings['creditcardmethod']) ? $this->settings['creditcardmethod'] : "redirect");
        $this->creditcardpayauthorize = (isset($this->settings['creditcardpayauthorize']) ? $this->settings['creditcardpayauthorize'] : "Pay");

        $this->supports = array(
            'products',
            'refunds'
        );
        $this->notify_url = home_url('/');
        
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

        } else {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_api_wc_gateway_buckaroo_creditcard', array( $this, 'response_handler' ) );
                $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Creditcard', $this->notify_url);
        }
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

        $action = ucfirst(isset($this->creditcardpayauthorize) ? $this->creditcardpayauthorize : 'pay');

        if ($action == 'Authorize') {
            $captures = get_post_meta($order_id, 'buckaroo_capture', false);
            $previous_refunds = get_post_meta($order_id, 'buckaroo_refund', false);


            if ($captures == false || count($captures) < 1) {
                return new WP_Error('error_refund_trid', __("Order is not captured yet, you can only refund captured orders"));
            }

            // Merge captures with previous refunds
            foreach ($captures as &$captureJson) {
                $capture = json_decode($captureJson, true);
                foreach ($previous_refunds as &$refundJson) {
                    $refund = json_decode($refundJson, true);
                    if (isset($refund['OriginalCaptureTransactionKey']) && $capture['OriginalTransactionKey'] == $refund['OriginalCaptureTransactionKey']) {
                        if ($capture['amount'] >= $refund['amount']) {
                            $capture['amount'] -= $refund['amount'];
                            $refund['amount'] = 0;
                        } else {
                            $refund['amount'] -= $capture['amount'];
                            $capture['amount'] = 0;
                        }
                    }
                    $refundJson = json_encode($refund);
                }
                $captureJson = json_encode($capture);
            }

            $captures = json_decode(json_encode($captures), true);

//            $line_item_qtys = json_decode(stripslashes($_POST['line_item_qtys']), true);
//            $line_item_totals = json_decode(stripslashes($_POST['line_item_totals']), true);
//            $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);

            $refundQueue = array();

            // Find free `slots` in captures
            foreach ($captures as $captureJson) {
                $capture = json_decode($captureJson, true);

                if ($amount > 0) {
                    if ($amount > $capture['amount']) {
                        $refundQueue[$capture['OriginalTransactionKey']] = $capture['amount'];
                        $amount -= $capture['amount'];
                    } else {
                        $refundQueue[$capture['OriginalTransactionKey']] = $amount;
                        $amount = 0;
                    }
                }
            }

            // Check if something cannot be refunded
            $NotRefundable = false;

            if ($amount > 0) {
                $NotRefundable = true;
            }

            if ($NotRefundable) {
                return new WP_Error('error_refund_trid', __("Refund amount cannot be bigger than the amount you have captured"));
            }            

            $refund_result = array();
            foreach ($refundQueue as $OriginalTransactionKey => $amount) {
                if ($amount > 0) {
                    $refund_result[] = $this->process_partial_refunds($order_id, $amount, $reason, $OriginalTransactionKey);
                }
            }  
            
            foreach ($refund_result as $result) {
                if ($result !== true) {
                    if (isset($result->errors['error_refund'][0])) {
                        return new WP_Error('error_refund_trid', __($result->errors['error_refund'][0]));
                    } else {
                        return new WP_Error('error_refund_trid', __("Unexpected error occured while processing refund, please check your transactions in the Buckaroo plaza."));
                    }
                }
            }

            return true;            

        } else {
            return $this->process_partial_refunds($order_id, $amount, $reason);
        }     
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_partial_refunds( $order_id, $amount = null, $reason = '', $OriginalTransactionKey = null,
                                             $line_item_totals = null, $line_item_tax_totals = null, $line_item_qtys = null)
    {
        $order = wc_get_order( $order_id );
        if ( ! $this->can_refund_order( $order ) ) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
//        $order = wc_get_order( $order_id );
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }

        $orderRefundData = [];

        if ($line_item_qtys === null) {
            $line_item_qtys = json_decode(stripslashes($_POST['line_item_qtys']), true);
        }

        if ($line_item_totals === null) {
            $line_item_totals = json_decode(stripslashes($_POST['line_item_totals']), true);
        }

        if ($line_item_tax_totals === null) {
            $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);
        }

//        foreach ($line_item_totals as $key => $value) {
//            if (!empty($value)) {
//                $orderRefundData[$key]['total'] = $value;
//            }
//        }
//
//        foreach ($line_item_tax_totals as $key => $keyItem) {
//            foreach ($keyItem as $taxItem => $taxItemValue) {
//                if (!empty($taxItemValue)) {
//                    $orderRefundData[$key]['tax'] = $taxItemValue;
//                }
//            }
//        }
//        if (!empty($line_item_qtys)){
//            foreach ($line_item_qtys as $key => $value) {
//                $orderRefundData[$key]['qty'] = $value;
//            }
//        }
//
//        $orderRefundData['totalRefund'] = 0;
//        foreach ($orderRefundData as $key => $item) {
//            $orderRefundData['totalRefund'] += $orderRefundData[$key]['total'] + $orderRefundData[$key]['tax'];
//        }

        $creditcard = new BuckarooCreditCard();
        $creditcard->amountDedit = 0;
        $creditcard->amountCredit = $amount;
        $creditcard->currency = $this->currency;
        $creditcard->description = $reason;
        $creditcard->invoiceId = $order_id;
        $creditcard->orderId = $order_id;

        if ($OriginalTransactionKey !== null) {
            $creditcard->OriginalTransactionKey = $OriginalTransactionKey;
        } else {
            $creditcard->OriginalTransactionKey = $order->get_transaction_id();
        }
        $creditcard->returnUrl = $this->notify_url;
        $clean_order_no = (int) str_replace('#', '', $order->get_order_number());
        $creditcard->setType(get_post_meta( $clean_order_no, '_payment_method_transaction', true));
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $creditcard->channel = BuckarooConfig::getChannel($payment_type, 'process_refund');
        $response = null;

        $orderDataForChecking = $creditcard->getOrderRefundData();

        try {
            $creditcard->checkRefundData($orderDataForChecking);
            $response = $creditcard->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }

        $final_response = fn_buckaroo_process_refund($response, $order, $amount, $this->currency);

        if ($final_response === true) {
            // Store the transaction_key together with refunded products, we need this for later refunding actions
            $refund_data = json_encode(['OriginalTransactionKey' => $response->transactions, 'OriginalCaptureTransactionKey' => $creditcard->OriginalTransactionKey, 'amount' => $amount]);
            add_post_meta($order_id, 'buckaroo_refund', $refund_data, false);                 
        }

        return $final_response;

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
        $creditCardMethod = isset($this->creditcardmethod) ? $this->creditcardmethod : 'redirect';
        $creditCardPayAuthorize = isset($this->creditcardpayauthorize) ? $this->creditcardpayauthorize : 'pay';

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order = getWCOrder($order_id);
        $creditcard = new BuckarooCreditCard();
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }
        if (method_exists($order, 'get_order_total')) {
            $creditcard->amountDedit = $order->get_order_total();
        } else {
            $creditcard->amountDedit = $order->get_total();
        }
        
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $creditcard->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $creditcard->currency = $this->currency;
        $creditcard->description = $this->transactiondescription;
        $creditcard->invoiceId = (string)getUniqInvoiceId($order_id);
        $creditcard->orderId = (string)$order_id;
        $creditcard->returnUrl = $this->notify_url;

        $customVars = Array();

        if (isset($_POST["buckaroo-encrypted-data"])) {
            $customVars['CreditCardDataEncrypted'] = $_POST["buckaroo-encrypted-data"];
        } else {
            $customVars['CreditCardDataEncrypted'] = null;
        }

        if (isset($_POST["buckaroo-creditcard-issuer"])) {
            $customVars['CreditCardIssuer'] = $_POST["buckaroo-creditcard-issuer"];
        } else {
            $customVars['CreditCardIssuer'] = null;
        }
        // Save this meta that is used later for the Capture call
        update_post_meta( $order->get_id(), '_wc_order_payment_issuer', $_POST["buckaroo-creditcard-issuer"] );
        update_post_meta( $order->get_id(), '_wc_order_selected_payment_method', 'Creditcard' );

        if ($creditCardMethod == 'encrypt' && $this->isSecure()) {
            // In this case we only send the encrypted card data.
            
            // If not then send an error.
            if (empty($_POST["buckaroo-encrypted-data"])) {
                wc_add_notice( __("The credit card data is incorrect, please check the values", 'wc-buckaroo-bpe-gateway'), 'error' );
                return;
            }

            if (empty($_POST["buckaroo-creditcard-issuer"])) {
                wc_add_notice( __("You havent selected your credit card issuer", 'wc-buckaroo-bpe-gateway'), 'error' );
                return;
            }

            $creditcard->CreditCardDataEncrypted = $_POST["buckaroo-encrypted-data"];
            // $customVars['CreditCardDataEncrypted'] = $creditcard->CreditCardDataEncrypted;

            if ($creditCardPayAuthorize == 'pay'){
                $response = $creditcard->PayEncrypt($customVars);
            } else if ($creditCardPayAuthorize == 'authorize'){
                $response = $creditcard->AuthorizeEncrypt($customVars, $order);
            } else {
                wc_add_notice( __("The type of credit card request is not defined. Contact Buckaroo.", 'wc-buckaroo-bpe-gateway'), 'error' );
                return;
            }

            return fn_buckaroo_process_response($this, $response);
        }

        if ($this->usenotification == 'TRUE') {
            $creditcard->usenotification = 1;
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
        if ($creditCardPayAuthorize == 'pay') {
            $response = $creditcard->Pay($customVars);
        } else if ($creditCardPayAuthorize == 'authorize'){
            $response = $creditcard->AuthorizeCC($customVars, $order);
        } else {
            wc_add_notice( __("The type of credit card request is not defined. Contact Buckaroo.", 'wc-buckaroo-bpe-gateway'), 'error' );
            return;
        }

        return fn_buckaroo_process_response($this, $response);
    }

    function process_capture(){
        $order_id = $_POST['order_id'];
        $woocommerce = getWooCommerceObject();
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $creditcard = new BuckarooCreditCard();
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }

        $order = getWCOrder($order_id);

        $customVars['CreditCardIssuer'] = get_post_meta( $order->get_id(), '_wc_order_payment_issuer', true);

        $creditcard->amountDedit = $_POST['capture_amount'];
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $creditcard->OriginalTransactionKey = $order->get_transaction_id();
        $creditcard->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $creditcard->currency = $this->currency;
        $creditcard->description = $this->transactiondescription;
        $creditcard->invoiceId = (string)getUniqInvoiceId($order_id);
        $creditcard->orderId = (string)$order_id;
        $creditcard->returnUrl = $this->notify_url;

        $response = $creditcard->Capture($customVars);

        // Store the transaction_key together with captured amount, we need this for refunding
        $capture_data = json_encode(array('OriginalTransactionKey' => $response->transactions, 'amount' => $creditcard->amountDedit));
        add_post_meta($order_id, 'buckaroo_capture', $capture_data, false); 

        return fn_buckaroo_process_capture($response, $order, $this->currency);

    }

    /**
     * Payment form on checkout page
     */
    function payment_fields() {

        $creditCardMethod = isset($this->creditcardmethod) ? $this->creditcardmethod : 'redirect';
        $creditCardPayAuthorize = isset($this->creditcardpayauthorize) ? $this->creditcardpayauthorize : 'pay';

        $post_data = Array();
        if (!empty($_POST["post_data"])) {
            parse_str($_POST["post_data"], $post_data);
        }

        ?>

        <?php if ($this->mode=='test') : ?><p><?php _e('TEST MODE', 'wc-buckaroo-bpe-gateway'); ?></p><?php endif; ?>
        <?php if ($this->description) : ?><p><?php echo wpautop(wptexturize($this->description)); ?></p><?php endif; ?>

                <fieldset>
                <div class="method--bankdata">

                <p class="form-row form-row-wide">
                <select name='buckaroo-creditcard-issuer' id='buckaroo-creditcard-issuer'>
                    <?php $first = true; ?>
                    <option value='0'  style='color: grey !important'>
                        <?php echo __('Select your credit card:', 'wc-buckaroo-bpe-gateway')?>
                    </option>
                    <?php foreach($this::getCardsList() as $issuer) : ?>
                        <div>
                            <option value='<?php echo $issuer['servicename']; ?>'>
                                <?php echo _e($issuer['displayname'], 'wc-buckaroo-bpe-gateway')?>
                            </option>
                        </div>
                        <?php $first = false; ?>
                    <?php endforeach ?>
                </select>
                </p>

                <?php if ($creditCardMethod == 'encrypt' && $this->isSecure()) { ?>

                <p class="form-row">
                <label class="buckaroo-label" for="buckaroo-creditcard-cardname">
                <?php echo _e('Cardholder Name:', 'wc-buckaroo-bpe-gateway')?>
                <span class="required">*</span>
                </label>
                <input type="text" name="buckaroo-creditcard-cardname" id="buckaroo-creditcard-cardname" placeholder="Cardholder Name" class="cardHolderName input-text" maxlength="250" autocomplete="off" value="">
                </p>

                <p class="form-row">
                <label class="buckaroo-label" for="buckaroo-creditcard-cardnumber">
                <?php echo _e('Card Number:', 'wc-buckaroo-bpe-gateway')?>
                <span class="required">*</span>
                </label>
                <input type="text" name="buckaroo-creditcard-cardnumber" id="buckaroo-creditcard-cardnumber" placeholder="Card Number" class="cardNumber input-text" maxlength="250" autocomplete="off" value="">
                </p>

                <p class="form-row">
                <label class="buckaroo-label" for="buckaroo-creditcard-cardcvc">
                <?php echo _e('CVC:', 'wc-buckaroo-bpe-gateway')?>
                <span class="required">*</span>
                </label>
                <input type="text" maxlength="4" name="buckaroo-creditcard-cardcvc" id="buckaroo-creditcard-cardcvc" placeholder="CVC" class="cvc input-text" maxlength="250" autocomplete="off" value="">
                </p>

                <p class="form-row">
                <label class="buckaroo-label" for="buckaroo-creditcard-cardyear">
                <?php echo _e('Expiration Year:', 'wc-buckaroo-bpe-gateway')?>
                <span class="required">*</span>
                </label>
                <input type="text" maxlength="4"  name="buckaroo-creditcard-cardyear" id="buckaroo-creditcard-cardyear" placeholder="Expiration Year" class="expirationYear input-text" maxlength="250" autocomplete="off" value="">
                </p>

                <p class="form-row">
                <label class="buckaroo-label" for="buckaroo-creditcard-cardmonth">
                <?php echo _e('Expiration Month:', 'wc-buckaroo-bpe-gateway')?>
                <span class="required">*</span>
                </label>
                <input type="text" maxlength="2" name="buckaroo-creditcard-cardmonth" id="buckaroo-creditcard-cardmonth" placeholder="Expiration Month" class="expirationMonth input-text" maxlength="250" autocomplete="off" value="">
                </p>
                
                <p class="form-row form-row-wide validate-required"></p>
                <p class="required" style="float:right;">* <?php echo _e('Obligatory fields', 'wc-buckaroo-bpe-gateway')?></p>

                <input type="hidden" id="buckaroo-encrypted-data" name="buckaroo-encrypted-data" class="encryptedCardData input-text">
            <?php } ?>

                </div>
                </fieldset>

        <?php
    }

    /**
     * Check response data
     * 
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

    public function getCardsList() {
        $cards = array();
        $cardsDesc = array("amex" => "American Express",
                           "cartebancaire" => "Carte Bancaire",
                           "cartebleuevisa" => "Carte Bleue",
                           "dankort" => "Dankort",
                           "mastercard" => "Mastercard",
                           "visa" => "Visa",
                           "visaelectron" => "Visa Electron",
                           "vpay" => "Vpay",
                           "maestro" => "Maestro");
        if (is_array($this->creditCardProvider)) {
            foreach ($this->creditCardProvider as $value) {
                $cards[] = array("servicename" => $value, "displayname" => $cardsDesc[$value]);
            }
        }
        return $cards;
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

        $this->form_fields['creditcardmethod'] = array(
            'title' => __( 'Credit card method', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'select',
            'description' => __( 'Redirect user to Buckaroo or enter creditcard information inline in the checkout. SSL is required to enable inline creditcard information', 'wc-buckaroo-bpe-gateway' ),
            'options' => array('redirect' => 'Redirect', 'encrypt' => 'Inline'),
            'default' => 'encrypt');

        $this->form_fields['creditcardpayauthorize'] = array(
            'title' => __( 'Credit card Pay or Capture', 'wc-buckaroo-bpe-gateway' ),
            'type' => 'select',
            'description' => __( 'Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway' ),
            'options' => array('pay' => 'Pay', 'authorize' => 'Authorize'),
            'default' => 'pay');

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


        $this->form_fields['AllowedProvider'] = array(
            'title' => __('Allowed provider', 'Allowed provider'),
            'type' => 'multiselect',
            'options' => array(
                'amex' => 'American Express',
                'cartebancaire' => 'Carte Bancaire',
                'cartebleuevisa' => 'Carte Bleue',
                'dankort' => 'Dankort',
                'mastercard' => 'Mastercard',
                'visa' => 'Visa',
                'visaelectron' => 'Visa Electron',
                'vpay' => 'Vpay',
                'maestro' => "Maestro",
            ),
            'description' => __('select which Creditecard providers  will be appear to customer', 'wc-buckaroo-bpe-gateway'),
            'default' => array('amex', 'cartebancaire', 'cartebleuevisa', 'dankort', 'mastercard','visa', 'visaelectron', 'vpay', 'maestro')
        );

    }


    /**
     * Returns true if secure (https), false if not (http)
     */
    function isSecure() {
        return
          (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || $_SERVER['SERVER_PORT'] == 443;
    }

}
