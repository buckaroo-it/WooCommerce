<?php
require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/billink/billink.php');

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Billink extends WC_Gateway_Buckaroo
{
    var $type;
    var $b2b;
    var $showpayproc;
    var $vattype;
    var $country;

    function __construct()
    {
        $woocommerce = getWooCommerceObject();

        $this->id = 'buckaroo_billink';
        $this->title = 'Billink';
        $this->icon = apply_filters('woocommerce_buckaroo_billink_icon', plugins_url('library/buckaroo_images/24x24/billink.png', __FILE__));
        $this->has_fields = true;
        $this->method_title = 'Buckaroo Billink';
        $this->description = "Betaal met Billink";
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $this->currency = get_woocommerce_currency();
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');

        $this->secretkey = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode = BuckarooConfig::getMode();
        $this->thumbprint = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture = BuckarooConfig::get('CULTURE');
        $this->usenotification = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');

        $country = null;
        if (!empty($woocommerce->customer)) {
            $country = get_user_meta($woocommerce->customer->get_id(), 'shipping_country', true);
        }

        $this->country = $country;

        parent::__construct();

        $this->supports = array(
            'products',
            'refunds'
        );
        $this->type = 'billink';
        $this->b2b = ($this->settings['enable_bb'] == 'B2B');

        $this->vattype = (isset($this->settings['vattype']) ? $this->settings['vattype'] : null);
        $this->notify_url = home_url('/');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '<')) {

        } else {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_buckaroo_billink', array($this, 'response_handler'));
//            if ($this->showpayproc) add_action( 'woocommerce_thankyou_buckaroo_billink' , array( $this, 'thankyou_description' ) );
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Billink', $this->notify_url);
        }
//        add_action( 'woocommerce_api_callback', 'response_handler' );

    }

    /**
     * Can the order be refunded
     * @access public
     * @param object $order WC_Order
     * @return object & string
     */
    public function can_refund_order($order)
    {
        return $order && $order->get_transaction_id();
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    function process_payment($order_id) {
        // Save this meta that is used later for the Capture call
        update_post_meta( $order_id, '_wc_order_selected_payment_method', 'Billink' );
        update_post_meta( $order_id, '_wc_order_payment_issuer', $this->type);

        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order = new WC_Order( $order_id );
        $billink = new BuckarooBillink();
        $billink->B2B = ($this->settings['enable_bb'] == 'B2B');
        if (checkForSequentialNumbersPlugin()) {
            if (preg_match('/\./i', $order->get_order_number())) {
                $order_id = preg_replace('/\./', '-', $order->get_order_number());
            } else {
//                $order_id = wc_seq_order_number_pro()->find_order_by_order_number( $order_id );
                $order_id = $order->get_order_number(); //Use sequential id
            }
//            $order_id = $order->get_order_number(); //Use sequential id

        }
        if (method_exists($order, 'get_order_total')) {
            $billink->amountDedit = $order->get_order_total();
        } else {
            $billink->amountDedit = $order->get_total();
        }
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $billink->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $billink->currency = $this->currency;

        $billink->description = 'Billink Pay';
//        $billink->description = $this->transactiondescription;
        $billink->invoiceId = getUniqInvoiceId(!empty($order_sequential_id) ? $order_sequential_id : (string)$order_id, $this->mode);
//        $billink->orderId = !empty($order_sequential_id) ? $order_sequential_id : (string)$order_id;
        $billink->orderId = !empty($order_sequential_id) ? $order_sequential_id : (string)$order_id;

        $billink->BillingGender = $_POST['buckaroo-billink-gender'];

        $get_billing_first_name = getWCOrderDetails($order_id, "billing_first_name");
        $get_billing_last_name = getWCOrderDetails($order_id, "billing_last_name");
        $get_billing_email = getWCOrderDetails($order_id, "billing_email");

        $billinkCategory = $this->settings['enable_bb'];
        $billink->setCategory($billinkCategory);

        $billink->BillingInitials = $this->getInitials($get_billing_first_name . ' ' . $get_billing_last_name);
        $billink->setBillingFirstName( $get_billing_first_name );
//        $billink->BillingFirstName = $get_billing_first_name;
        $billink->BillingLastName = $get_billing_last_name;
        $birthdate = $_POST['buckaroo-billink-birthdate'];
        if (!empty($_POST["buckaroo-billink-b2b"]) && $_POST["buckaroo-billink-b2b"] == 'ON') {
            // if is company reset birthdate
            $birthdate = '01-01-1990';
        }
//        if ($this->validateDate($birthdate,'d-m-Y')){
//            $birthdate = date('Y-m-d', strtotime($birthdate));
//        } elseif (in_array(getWCOrderDetails($order_id, 'billing_country'), ['NL', 'BE'])) {
//            wc_add_notice( __("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error' );
//            return;
//        }
//        if (empty($_POST["buckaroo-billink-accept"])) {
//            wc_add_notice( __("Please accept licence agreements", 'wc-buckaroo-bpe-gateway'), 'error' );
//            return;
//        }
        $shippingCosts = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $billink->ShippingCosts = number_format($shippingCosts, 2)+number_format($shippingCostsTax, 4);
        }
        if (floatval($shippingCostsTax) > 0) {
            $billink->ShippingCostsTax = number_format(($shippingCostsTax * 100) / $shippingCosts);
        }


        $billink->BillingBirthDate = $birthdate;
        // Set birthday if it's NL or BE
//        $billink->BillingBirthDate = date('Y-m-d', strtotime($birthdate));

        if ($billink->B2B) {
            $billink->CompanyCOCRegistration = $_POST['buckaroo-billink-CompanyCOCRegistration'];
            $billink->VatNumber = $_POST['buckaroo-billink-VatNumber'];
        }

        $get_billing_address_1 = getWCOrderDetails($order_id, 'billing_address_1');
        $get_billing_address_2 = getWCOrderDetails($order_id, 'billing_address_2');
        $address_components = fn_buckaroo_get_address_components($get_billing_address_1." ".$get_billing_address_2);
        $billink->BillingStreet = $address_components['street'];
        $billink->BillingHouseNumber = $address_components['house_number'];
        $billink->BillingHouseNumberSuffix = $address_components['number_addition'];
        $billink->BillingPostalCode = getWCOrderDetails($order_id, 'billing_postcode');
        $billink->BillingCity = getWCOrderDetails($order_id, 'billing_city');
        $billink->BillingCountry = getWCOrderDetails($order_id, 'billing_country');
        $get_billing_email = getWCOrderDetails($order_id, 'billing_email');
        $billink->BillingEmail = !empty($get_billing_email) ? $get_billing_email : '';
        $billink->BillingLanguage = 'nl';
        $get_billing_phone = getWCOrderDetails($order_id, 'billing_phone');
        $number = $this->cleanup_phone($get_billing_phone);
        $billink->BillingPhoneNumber = $number['phone'];

        $billink->AddressesDiffer = 'FALSE';
        if (isset($_POST["buckaroo-billink-shipping-differ"])) {
            // if (!empty($_POST["buckaroo-billink-shipping-differ"])) {
            $billink->AddressesDiffer = 'TRUE';

            $get_shipping_first_name = getWCOrderDetails($order_id, 'shipping_first_name');
            $billink->ShippingInitials = $this->getInitials($get_shipping_first_name);
            $billink->ShippingFirstName = $get_shipping_first_name;
            $get_shipping_last_name = getWCOrderDetails($order_id, 'shipping_last_name');
            $billink->ShippingLastName = $get_shipping_last_name;
            $get_shipping_address_1 = getWCOrderDetails($order_id, 'shipping_address_1');
            $get_shipping_address_2 = getWCOrderDetails($order_id, 'shipping_address_2');
            $address_components = fn_buckaroo_get_address_components($get_shipping_address_1." ".$get_shipping_address_2);
            $billink->ShippingStreet = $address_components['street'];
            $billink->ShippingHouseNumber = $address_components['house_number'];
            $billink->ShippingHouseNumberSuffix = $address_components['number_addition'];

            $billink->ShippingPostalCode = getWCOrderDetails($order_id, 'shipping_postcode');
            $billink->ShippingCity = getWCOrderDetails($order_id, 'shipping_city');
            $billink->ShippingCountryCode = getWCOrderDetails($order_id, 'shipping_country');
            $billink->ShippingGender = 'Male';

            $get_shipping_email = getWCOrderDetails($order_id, 'billing_email');
            $billink->ShippingEmail = !empty($get_shipping_email) ? $get_shipping_email : '';
//            $billink->ShippingLanguage = 'nl';
            $get_shipping_phone = getWCOrderDetails($order_id, 'billing_phone');
            $number = $this->cleanup_phone($get_shipping_phone);
            $billink->ShippingPhoneNumber = $number['phone'];
        }

        $billink->CustomerIPAddress = getClientIpBuckaroo();
        $billink->Accept = 'TRUE';
        $products = Array();
        $items = $order->get_items();
        $itemsTotalAmount = 0;

        $articlesLooped = [];

        $feeItemRate = 0;
        foreach ( $items as $item ) {

            $product = new WC_Product($item['product_id']);
//            $imgTag = $product->get_image();
//            $xpath = new DOMXPath(@DOMDocument::loadHTML($imgTag));
//            $src = $xpath->evaluate("string(//img/@src)");

            $tax = new WC_Tax();
            $taxes = $tax->get_rates($product->get_tax_class());
            $rates = array_shift($taxes);
            $itemRate = number_format(array_shift($rates),2);

            if($product->get_tax_status() != 'taxable'){
                $itemRate = 0;
            }

            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"] = $item['product_id'];
            $tmp["ArticleQuantity"] = $item["qty"];
            $tmp["ArticleUnitpriceExcl"] = number_format($item["line_total"] / $item["qty"], 4);
            $tmp["ArticleUnitpriceIncl"] = number_format(number_format($item["line_total"]+$item["line_tax"], 4)/$item["qty"], 4);
            $itemsTotalAmount += number_format($tmp["ArticleUnitpriceIncl"] * $item["qty"], 4);

            $tmp["ArticleVatcategory"] = $itemRate;
            $products[] = $tmp;
            $feeItemRate = $feeItemRate > $itemRate ? $feeItemRate : $itemRate;
        }

        $fees = $order->get_fees();
        foreach ( $fees as $key => $item ) {

            $feeTaxRate = $this->getFeeTax($fees[$key]);

            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"] = $key;
            $tmp["ArticleQuantity"] = 1;
            $tmp["ArticleUnitpriceExcl"] = number_format($item["line_total"], 4);
            $tmp["ArticleUnitpriceIncl"] = number_format(($item["line_total"]+$item["line_tax"]), 4);
            $itemsTotalAmount += $tmp["ArticleUnitpriceIncl"];
            $tmp["ArticleVatcategory"] = $feeTaxRate;
            $products[] = $tmp;
        }
        if(!empty($billink->ShippingCosts)) {
            $itemsTotalAmount += $billink->ShippingCosts;
        }

        if ($billink->amountDedit != $itemsTotalAmount) {
            if (number_format($billink->amountDedit - $itemsTotalAmount, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"] = 'remaining_price';
                $tmp["ArticleQuantity"] = 1;
                $tmp["ArticleUnitpriceExcl"] = number_format($billink->amountDedit - $itemsTotalAmount, 4);
                $tmp["ArticleVatcategory"] = 0;
                $products[] = $tmp;
                $itemsTotalAmount += 0.01;
            } elseif (number_format($itemsTotalAmount - $billink->amountDedit, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"] = 'remaining_price';
                $tmp["ArticleQuantity"] = 1;
                $tmp["ArticleUnitpriceExcl"] = number_format($billink->amountDedit - $itemsTotalAmount, 4);
                $tmp["ArticleVatcategory"] = 0;
                $products[] = $tmp;
                $itemsTotalAmount -= 0.01;
            }
        }

        $billink->returnUrl = $this->notify_url;

        if ($this->usenotification == 'TRUE') {
            $billink->usenotification = 1;
            $customVars['Customergender'] = $_POST['buckaroo-billink-gender'];

            $get_billing_first_name = getWCOrderDetails($order_id, 'billing_first_name');
            $get_billing_last_name = getWCOrderDetails($order_id, 'billing_last_name');
            $get_billing_email = getWCOrderDetails($order_id, 'billing_email');
            $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['CustomerLastName'] = !empty($get_billing_last_name) ? $get_billing_last_name : '';
            $customVars['Customeremail'] = !empty($get_billing_email) ? $get_billing_email : '';
            $customVars['Notificationtype'] = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int) $this->invoicedelay . ' day')).' + '. (int)$this->notificationdelay.' day'));
        }

        $response = $billink->PayOrAuthorizeBillink($products, 'Pay');
        return fn_buckaroo_process_response($this, $response, $this->mode);
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        //var_dump($this->b2b);die();

        $accountname = get_user_meta($GLOBALS["current_user"]->ID, 'billing_first_name', true)." ".get_user_meta($GLOBALS["current_user"]->ID, 'billing_last_name', true);
        $post_data = array();
        if (! empty($_POST["post_data"])) {
            parse_str($_POST["post_data"], $post_data);
        } ?>
        <?php if ($this->mode=='test') : ?>
        <p><?php _e('TEST MODE', 'wc-buckaroo-bpe-gateway'); ?>
        </p><?php endif; ?>
        <?php if ($this->description) : ?>
        <p><?php echo wpautop(wptexturize($this->description)); ?>
        </p><?php endif; ?>

        <fieldset>
            <?php if ($this->b2b) {
                ?>
                <p class="form-row form-row-wide validate-required">
                    <?php echo _e('Checkout for company', 'wc-buckaroo-bpe-gateway')?>
                    <input id="buckaroo-billink-b2b" name="buckaroo-billink-b2b" onclick="CheckoutFields(this.checked)"
                           type="checkbox" value="ON" />
                </p>

                <script>
                    function CheckoutFields(showFiields) {
                        if (showFiields) {
                            document.getElementById('showB2BBuckaroo').style.display = 'block';
                            //document.getElementById('buckaroo-billink-VatNumber').value = document.getElementById(
                            //    'billing_company').value;
                            document.getElementById('buckaroo-billink-birthdate').disabled = true;
                            document.getElementById('buckaroo-billink-birthdate').value = '';
                            document.getElementById('buckaroo-billink-birthdate').parentElement.style.display = 'none';
                            document.getElementById('buckaroo-billink-birthdate').parentElement.classList.remove(
                                'woocommerce-invalid');
                            document.getElementById('buckaroo-billink-birthdate').parentElement.classList.remove(
                                'validate-required');
                            document.getElementById('buckaroo-billink-genderm').disabled = true;
                            document.getElementById('buckaroo-billink-genderf').disabled = true;
                            document.getElementById('buckaroo-billink-genderm').parentElement.style.display = 'none';
                            document.getElementById('buckaroo-billink-genderm').parentElement.getElementsByTagName('span').item(0)
                                .style.display = 'none';
                        } else {
                            document.getElementById('showB2BBuckaroo').style.display = 'none';
                            document.getElementById('buckaroo-billink-birthdate').disabled = false;
                            document.getElementById('buckaroo-billink-birthdate').parentElement.style.display = 'block';
                            document.getElementById('buckaroo-billink-birthdate').parentElement.classList.add('validate-required');
                            document.getElementById('buckaroo-billink-genderm').disabled = false;
                            document.getElementById('buckaroo-billink-genderf').disabled = false;
                            document.getElementById('buckaroo-billink-genderf').parentElement.style.display = 'inline-block';
                            document.getElementById('buckaroo-billink-genderf').parentElement.getElementsByTagName('span').item(0)
                                .style.display = 'inline-block';
                        }
                    }
                </script>

                <span id="showB2BBuckaroo" style="display:none">
        <p class="form-row form-row-wide validate-required">
            <?php echo _e('Fill required fields if bill in on the company:', 'wc-buckaroo-bpe-gateway')?>
        </p>
        <p class="form-row form-row-wide validate-required">
            <label for="buckaroo-billink-CompanyCOCRegistration"><?php echo _e('COC (KvK) number:', 'wc-buckaroo-bpe-gateway')?><span
                    class="required">*</span></label>
            <input id="buckaroo-billink-CompanyCOCRegistration" name="buckaroo-billink-CompanyCOCRegistration"
                   class="input-text" type="text" maxlength="250" autocomplete="off" value="" />
        </p>
        <p class="form-row form-row-wide validate-required">
            <label for="buckaroo-billink-VatNumber"><?php echo _e('VAT number:', 'wc-buckaroo-bpe-gateway')?><span
                    class="required">*</span></label>
            <input id="buckaroo-billink-VatNumber" name="buckaroo-billink-VatNumber" class="input-text"
                   type="text" maxlength="250" autocomplete="off" value="" />
        </p>
    </span>
                <?php
            } ?>

            <p class="form-row">
                <label for="buckaroo-billink-gender"><?php echo _e('Gender:', 'wc-buckaroo-bpe-gateway')?><span
                        class="required">*</span></label>
                <input id="buckaroo-billink-genderm" name="buckaroo-billink-gender" class="" type="radio" value="Male" checked
                       style="float:none; display: inline !important;" /> <?php echo _e('Male', 'wc-buckaroo-bpe-gateway')?>
                &nbsp;
                <input id="buckaroo-billink-genderf" name="buckaroo-billink-gender" class="" type="radio" value="Female"
                       style="float:none; display: inline !important;" /> <?php echo _e('Female', 'wc-buckaroo-bpe-gateway')?>
            </p>
            <p class="form-row form-row-wide validate-required">
                <label for="buckaroo-billink-birthdate"><?php echo _e('Birthdate (format DD-MM-YYYY):', 'wc-buckaroo-bpe-gateway')?><span
                        class="required">*</span></label>
                <input id="buckaroo-billink-birthdate" name="buckaroo-billink-birthdate" class="input-text" type="text"
                       maxlength="250" autocomplete="off" value="" placeholder="DD-MM-YYYY" />
            </p>
            <?php if (! empty($post_data["ship_to_different_address"])) {
                ?>
                <input id="buckaroo-billink-shipping-differ" name="buckaroo-billink-shipping-differ" class="" type="hidden"
                       value="1" />
                <?php
            } ?>

            <p class="required" style="float:right;">* Verplicht</p>
        </fieldset>
        <?php
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, [$this, 'enqueue_script_certificate']);

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, [$this, 'enqueue_script_hide_local']);

        //Start Dynamic Rendering of Hidden Fields
        $options = get_option("woocommerce_".$this->id."_settings", null);
        $ccontent_arr = array();
        $keybase = 'certificatecontents';
        $keycount = 1;
        if (! empty($options["$keybase$keycount"])) {
            while (! empty($options["$keybase$keycount"])) {
                $ccontent_arr[] = "$keybase$keycount";
                $keycount++;
            }
        }
        $while_key = 1;
        $selectcertificate_options = ['none' => 'None selected'];
        while ($while_key != $keycount) {
            $this->form_fields["certificatecontents$while_key"] = [
                'title' => '',
                'type' => 'hidden',
                'description' => '',
                'default' => ''
            ];
            $this->form_fields["certificateuploadtime$while_key"] = [
                'title' => '',
                'type' => 'hidden',
                'description' => '',
                'default' => ''];
            $this->form_fields["certificatename$while_key"] = [
                'title' => '',
                'type' => 'hidden',
                'description' => '',
                'default' => ''];
            $selectcertificate_options["$while_key"] = $options["certificatename$while_key"];

            $while_key++;
        }
        $final_ccontent = $keycount;
        $this->form_fields["certificatecontents$final_ccontent"] = [
            'title' => '',
            'type' => 'hidden',
            'description' => '',
            'default' => ''];
        $this->form_fields["certificateuploadtime$final_ccontent"] = [
            'title' => '',
            'type' => 'hidden',
            'description' => '',
            'default' => ''];
        $this->form_fields["certificatename$final_ccontent"] = [
            'title' => '',
            'type' => 'hidden',
            'description' => '',
            'default' => ''];

        $this->form_fields['selectcertificate'] = [
            'title' => __('Select Certificate', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Select your certificate by name.', 'wc-buckaroo-bpe-gateway'),
            'options' => $selectcertificate_options,
            'default' => 'none'
        ];
        $this->form_fields['choosecertificate'] = [
            'title' => __('', 'wc-buckaroo-bpe-gateway'),
            'type' => 'file',
            'description' => __(''),
            'default' => ''];
//        $this->form_fields['service'] = [
//            'title' => __('Select billink service', 'wc-buckaroo-bpe-gateway'),
//            'type' => 'select',
//            'description' => __('Please select the service', 'wc-buckaroo-bpe-gateway'),
//            'options' => ['afterpayacceptgiro'=>'Offer customer to pay afterwards by SEPA Direct Debit.', 'afterpaydigiaccept'=>'Offer customer to pay afterwards by digital invoice.'],
//            'default' => 'afterpaydigiaccept'];

        $this->form_fields['enable_bb'] = [
            'title' => __('Mode', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
//            'description' => __('Enables or disables possibility to pay using company credentials', 'wc-buckaroo-bpe-gateway'),
            'options' => ['B2B'=>'B2B', 'B2C'=>'B2C'],
            'default' => 'B2C'];

        $this->form_fields['usenotification'] = [
            'title' => __('Use Notification Service', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('The notification service can be used to have the payment engine sent additional notifications.', 'wc-buckaroo-bpe-gateway'),
            'options' => ['TRUE'=>'Yes', 'FALSE'=>'No'],
            'default' => 'FALSE'];

        $this->form_fields['notificationdelay'] = [
            'title' => __('Notification delay', 'wc-buckaroo-bpe-gateway'),
            'type' => 'text',
            'description' => __('The time at which the notification should be sent. If this is not specified, the notification is sent immediately.', 'wc-buckaroo-bpe-gateway'),
            'default' => '0'];
    }

    private function getFeeTax($fee) {
        $feeInfo = WC_Tax::get_rates($fee->get_tax_class());
        $feeInfo = array_shift($feeInfo);
        $feeTaxRate = $feeInfo['rate'] ?? 0;

        return $feeTaxRate;
    }
}
