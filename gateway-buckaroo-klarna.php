<?php
require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/klarna/klarna.php');

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Klarna extends WC_Gateway_Buckaroo {

    protected $type;
    protected $currency;
    protected $klarnaPaymentFlowId = '';
    protected $klarnaSelector = '';

    function __construct() {
        parent::__construct();
    }

    public function getKlarnaSelector() {
        return $this->klarnaSelector;
    }

    public function getKlarnaPaymentFlow() {
        return $this->klarnaPaymentFlowId;
    }
    /**
     * Can the order be refunded
     * @access public
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
        $klarna = new BuckarooKlarna();
        $klarna->amountDedit = 0;
        $klarna->amountCredit = $amount;
        $klarna->currency = $this->currency;
        $klarna->description = $reason;
        $klarna->invoiceId = $order_id;
        $klarna->orderId = $order_id;
        $klarna->OriginalTransactionKey = $order->get_transaction_id();
        $klarna->returnUrl = $this->notify_url;
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $klarna->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response = null;

        $orderDataForChecking = $klarna->getOrderRefundData();

        try {
            $klarna->checkRefundData($orderDataForChecking);
            $response = $klarna->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
     */
    public function validate_fields()
    {

    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    function process_payment($order_id)
    {
        // Save this meta that is used later for the Capture call
        update_post_meta( $order_id, '_wc_order_selected_payment_method', 'Klarna' );
        update_post_meta( $order_id, '_wc_order_payment_issuer', $this->type);

        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';

        $order = new WC_Order( $order_id );
        $klarna = new BuckarooKlarna($this->type);
        if (checkForSequentialNumbersPlugin()) {
            if (preg_match('/\./i', $order->get_order_number())) {
                $order_id = preg_replace('/\./', '-', $order->get_order_number());
            } else {
                $order_id = $order->get_order_number(); //Use sequential id
            }
        }
        if (method_exists($order, 'get_order_total')) {
            $klarna->amountDedit = $order->get_order_total();
        } else {
            $klarna->amountDedit = $order->get_total();
        }
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $klarnaSelector = $this->getKlarnaSelector();

        $klarna->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $klarna->currency = $this->currency;
        $klarna->description = $this->transactiondescription;
        $klarna->invoiceId = getUniqInvoiceId(!empty($order_sequential_id) ? $order_sequential_id : (string)$order_id, $this->mode);

        $klarna->orderId = !empty($order_sequential_id) ? $order_sequential_id : (string)$order_id;

        $klarna->BillingGender = $_POST[$this->klarnaSelector.'-gender'] ?? 'Unknown';

        $get_billing_first_name = getWCOrderDetails($order_id, "billing_first_name");
        $get_billing_last_name = getWCOrderDetails($order_id, "billing_last_name");

        $klarna->BillingFirstName = $get_billing_first_name;
        $klarna->BillingLastName = $get_billing_last_name;

        $shippingCosts = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $klarna->ShippingCosts = number_format($shippingCosts, 2)+number_format($shippingCostsTax, 2);
        }
        if (floatval($shippingCostsTax) > 0) {
            $klarna->ShippingCostsTax = number_format(($shippingCostsTax * 100) / $shippingCosts);
        }

        $get_billing_address_1 = getWCOrderDetails($order_id, 'billing_address_1');
        $get_billing_address_2 = getWCOrderDetails($order_id, 'billing_address_2');

        $billingCompany = getWCOrderDetails($order_id,'billing_company');
        $klarna->setBillingCategory($billingCompany);
        $klarna->setShippingCategory($billingCompany);


        $address_components = fn_buckaroo_get_address_components($get_billing_address_1." ".$get_billing_address_2);
        $klarna->BillingStreet = $address_components['street'];
        $klarna->BillingHouseNumber = $address_components['house_number'];
        $klarna->BillingHouseNumberSuffix = $address_components['number_addition'] ?? null;
        $klarna->BillingPostalCode = getWCOrderDetails($order_id, 'billing_postcode');
        $klarna->BillingCity = getWCOrderDetails($order_id, 'billing_city');
        $klarna->BillingCountry = getWCOrderDetails($order_id, 'billing_country');
        $get_billing_email = getWCOrderDetails($order_id, 'billing_email');
        $klarna->BillingEmail = !empty($get_billing_email) ? $get_billing_email : '';
        $klarna->BillingLanguage = 'nl';
        $get_billing_phone = getWCOrderDetails($order_id, 'billing_phone');
        $number = $this->cleanup_phone($get_billing_phone);
        $klarna->BillingPhoneNumber = !empty($number['phone']) ? $number['phone'] : $_POST[$this->getKlarnaSelector()."-phone"];

        $klarna->AddressesDiffer = 'FALSE';
        if (isset($_POST[$this->getKlarnaSelector()."-shipping-differ"])) {
            $klarna->AddressesDiffer = 'TRUE';

            $shippingCompany = getWCOrderDetails($order_id,'shipping_company');
            $klarna->setShippingCategory($shippingCompany);

            $get_shipping_first_name = getWCOrderDetails($order_id, 'shipping_first_name');
            $klarna->ShippingFirstName = $get_shipping_first_name;
            $get_shipping_last_name = getWCOrderDetails($order_id, 'shipping_last_name');
            $klarna->ShippingLastName = $get_shipping_last_name;
            $get_shipping_address_1 = getWCOrderDetails($order_id, 'shipping_address_1');
            $get_shipping_address_2 = getWCOrderDetails($order_id, 'shipping_address_2');
            $address_components = fn_buckaroo_get_address_components($get_shipping_address_1." ".$get_shipping_address_2);
            $klarna->ShippingStreet = $address_components['street'];
            $klarna->ShippingHouseNumber = $address_components['house_number'];
            $klarna->ShippingHouseNumberSuffix = $address_components['number_addition'];

            $klarna->ShippingPostalCode = getWCOrderDetails($order_id, 'shipping_postcode');
            $klarna->ShippingCity = getWCOrderDetails($order_id, 'shipping_city');
            $klarna->ShippingCountryCode = getWCOrderDetails($order_id, 'shipping_country');


            $get_shipping_email = getWCOrderDetails($order_id, 'billing_email');
            $klarna->ShippingEmail = !empty($get_shipping_email) ? $get_shipping_email : '';
            $klarna->ShippingLanguage = 'nl';
            $get_shipping_phone = getWCOrderDetails($order_id, 'billing_phone');
            $number = $this->cleanup_phone($get_shipping_phone);
            $klarna->ShippingPhoneNumber = $number['phone'];
        }

        if ($_POST['shipping_method'][0] == 'dhlpwc-parcelshop') {
            $dhlConnectorData = $order->get_meta('_dhlpwc_order_connectors_data');
            $dhlCountry = !empty($this->country) ? $this->country : $_POST['billing_country'];
            $requestPart = $dhlCountry . '/' . $dhlConnectorData['id'];
            $dhlParcelShopAddressData = $this->getDHLParcelShopLocation($requestPart);
            $klarna->AddressesDiffer = 'TRUE';
            $klarna->ShippingStreet = $dhlParcelShopAddressData->street;
            $klarna->ShippingHouseNumber = $dhlParcelShopAddressData->number;
            $klarna->ShippingPostalCode = $dhlParcelShopAddressData->postalCode;
            $klarna->ShippingHouseNumberSuffix = '';
            $klarna->ShippingCity = $dhlParcelShopAddressData->city;
            $klarna->ShippingCountryCode = $dhlParcelShopAddressData->countryCode;
        }

        if (!empty($_POST['post-deliver-or-pickup']) && $_POST['post-deliver-or-pickup'] == 'post-pickup') {
            $postNL = $order->get_meta('_postnl_delivery_options');
            $klarna->AddressesDiffer = 'TRUE';
            $klarna->ShippingStreet = $postNL['street'];
            $klarna->ShippingHouseNumber = $postNL['number'];
            $klarna->ShippingPostalCode = $postNL['postal_code'];
            $klarna->ShippingHouseNumberSuffix = trim(str_replace('-',' ', $postNL['number_suffix']));
            $klarna->ShippingCity = $postNL['city'];
            $klarna->ShippingCountryCode = $postNL['cc'];
        }

        if (!empty($_POST['sendcloudshipping_service_point_selected'])) {
            $klarna->AddressesDiffer = 'TRUE';
            $sendcloudPointAddress = $order->get_meta('sendcloudshipping_service_point_meta');
            $addressData =  $this->parseSendCloudPointAddress($sendcloudPointAddress['extra']);

            $klarna->ShippingStreet = $addressData['street']['name'];
            $klarna->ShippingHouseNumber = $addressData['street']['house_number'];
            $klarna->ShippingPostalCode = $addressData['postal_code'];
            $klarna->ShippingHouseNumberSuffix = $addressData['street']['number_addition'];
            $klarna->ShippingCity = $addressData['city'];
            $klarna->ShippingCountryCode = $klarna->BillingCountry;
        }

        if (isset($_POST['_myparcel_delivery_options'])) {
            $myparselDeliveryOptions = $order->get_meta('_myparcel_delivery_options');
            if (!empty($myparselDeliveryOptions)) {
                $myparselDeliveryOptions = unserialize($myparselDeliveryOptions);
            }
            if ($myparselDeliveryOptions->isPickup()) {
                $klarna->AddressesDiffer = 'TRUE';
                $pickupOptions = $myparselDeliveryOptions->getPickupLocation();
                $klarna->ShippingStreet = $pickupOptions->getStreet();
                $klarna->ShippingHouseNumber = $pickupOptions->getNumber();
                $klarna->ShippingPostalCode = $pickupOptions->getPostalCode();;
                $klarna->ShippingCity = $pickupOptions->getCity();
                $klarna->ShippingCountryCode = $pickupOptions->getCountry();
            }
        }

        $klarna->CustomerIPAddress = getClientIpBuckaroo();
        $klarna->Accept = 'TRUE';
        $products = Array();
        $items = $order->get_items();
        $itemsTotalAmount = 0;

        $articlesLooped = [];

        $feeItemRate = 0;
        foreach ( $items as $item ) {

            $product = new WC_Product($item['product_id']);
            $imgTag = $product->get_image();
            $xpath = new DOMXPath(@DOMDocument::loadHTML($imgTag));
            $src = $xpath->evaluate("string(//img/@src)");

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
            $tmp["ArticleUnitprice"] = number_format(number_format($item["line_total"]+$item["line_tax"], 4)/$item["qty"], 2);
            $itemsTotalAmount += number_format($tmp["ArticleUnitprice"] * $item["qty"], 2);

            $tmp["ArticleVatcategory"] = $itemRate;
            $tmp["ProductUrl"] = get_permalink($item['product_id']);
            $tmp["ImageUrl"] = $src;
            $products[] = $tmp;
            $feeItemRate = $feeItemRate > $itemRate ? $feeItemRate : $itemRate;
        }

        $fees = $order->get_fees();
        foreach ( $fees as $key => $item ) {

            $feeTaxRate = $this->getFeeTax($fees[$key]);

            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"] = $key;
            $tmp["ArticleQuantity"] = 1;
            $tmp["ArticleUnitprice"] = number_format(($item["line_total"]+$item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $tmp["ArticleVatcategory"] = $feeTaxRate;
            $products[] = $tmp;
        }
        if(!empty($klarna->ShippingCosts)) {
            $itemsTotalAmount += $klarna->ShippingCosts;
        }

        if ($klarna->amountDedit != $itemsTotalAmount) {
            if (number_format($klarna->amountDedit - $itemsTotalAmount, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"] = 'remaining_price';
                $tmp["ArticleQuantity"] = 1;
                $tmp["ArticleUnitprice"] = number_format($klarna->amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"] = 0;
                $products[] = $tmp;
                $itemsTotalAmount += 0.01;
            } elseif (number_format($itemsTotalAmount - $klarna->amountDedit, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"] = 'remaining_price';
                $tmp["ArticleQuantity"] = 1;
                $tmp["ArticleUnitprice"] = number_format($klarna->amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"] = 0;
                $products[] = $tmp;
                $itemsTotalAmount -= 0.01;
            }
        }

        $klarna->returnUrl = $this->notify_url;

        if ($this->usenotification == 'TRUE') {
            $klarna->usenotification = 1;
            $customVars['Customergender'] = $_POST[$this->getKlarnaSelector().'-gender'];

            $get_billing_first_name = getWCOrderDetails($order_id, 'billing_first_name');
            $get_billing_last_name = getWCOrderDetails($order_id, 'billing_last_name');
            $get_billing_email = getWCOrderDetails($order_id, 'billing_email');
            $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['CustomerLastName'] = !empty($get_billing_last_name) ? $get_billing_last_name : '';
            $customVars['Customeremail'] = !empty($get_billing_email) ? $get_billing_email : '';
            $customVars['Notificationtype'] = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int) $this->invoicedelay . ' day')).' + '. (int)$this->notificationdelay.' day'));
        }

        $klarna->setPaymnetFlow($this->getKlarnaPaymentFlow());
        $response = $klarna->paymentAction($products);
        return fn_buckaroo_process_response($this, $response, $this->mode);
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
        if (!is_null($result)){
            wp_safe_redirect($result['redirect']);
        } else {
            wp_safe_redirect($this->get_failed_url());
        }
    }

    private function getFeeTax($fee) {
        $feeInfo = WC_Tax::get_rates($fee->get_tax_class());
        $feeInfo = array_shift($feeInfo);
        $feeTaxRate = $feeInfo['rate'] ?? 0;

        return $feeTaxRate;
    }

    public function formatStreet($street)
    {
        $format = [
            'house_number'    => '',
            'number_addition' => '',
            'name'          => $street
        ];

        if (preg_match('#^(.*?)([0-9\-]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['house_number'] = trim($matches[2]);
                $format['name']       = trim($matches[3]);
            } else {
                if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
                    $format['name']          = trim($matches[1]);
                    $format['house_number']    = trim($matches[2]);
                    $format['number_addition'] = trim($matches[3]);
                }
            }
        }

        return $format;
    }

    /**
     * Payment form on checkout page
     */
    function payment_fields()
    {
        $accountname = get_user_meta($GLOBALS["current_user"]->ID, 'billing_first_name', true) . " " . get_user_meta($GLOBALS["current_user"]->ID, 'billing_last_name', true);
        $post_data = Array();

        $customerId = get_current_user_id();
        $customerPhone = '';
        if (!empty($customerId)) {
            $customerInfo = get_user_meta($customerId);
            $customerPhone = get_user_meta($customerId, 'billing_phone', true);
        }

        if (!empty($_POST["post_data"])) {
            parse_str($_POST["post_data"], $post_data);
        }
        ?>
        <?php if ($this->mode == 'test') : ?><p><?php _e('TEST MODE', 'wc-buckaroo-bpe-gateway'); ?></p><?php endif; ?>
        <?php if ($this->description) : ?><p><?php echo wpautop(wptexturize($this->description)); ?></p><?php endif; ?>

        <fieldset>
            <p class="form-row">
                <label for="<?php echo $this->getKlarnaSelector() ?>-gender"><?php echo _e('Gender:', 'wc-buckaroo-bpe-gateway') ?><span
                            class="required">*</span></label>
                <input id="<?php echo $this->getKlarnaSelector() ?>-genderm" name="<?php echo $this->getKlarnaSelector() ?>-gender" class="" type="radio"
                       value="Male" checked
                       style="float:none; display: inline !important;"/> <?php echo _e('Male', 'wc-buckaroo-bpe-gateway') ?>
                &nbsp;
                <input id="<?php echo $this->getKlarnaSelector() ?>-genderf" name="<?php echo $this->getKlarnaSelector() ?>-gender" class="" type="radio"
                       value="Female"
                       style="float:none; display: inline !important;"/> <?php echo _e('Female', 'wc-buckaroo-bpe-gateway') ?>
            </p>
            <p class="form-row validate-required">
                <label for="<?php echo $this->getKlarnaSelector() ?>-phone"><?php echo _e('Phone:', 'wc-buckaroo-bpe-gateway') ?><span
                            class="required">*</span></label>
                <input id="<?php echo $this->getKlarnaSelector() ?>-phone" name="<?php echo $this->getKlarnaSelector() ?>-phone" class="input-tel"
                       type="tel" autocomplete="off" value="<?php echo $customerPhone ?? '' ?>">
            </p>

            <script>
                if (document.querySelector('input[name=billing_phone]')) {
                    document.getElementById('<?php echo $this->getKlarnaSelector() ?>-phone').parentElement.style.display = 'none';
                }
            </script>

            <?php if (!empty($post_data["ship_to_different_address"])) { ?>
                <input id="<?php echo $this->getKlarnaSelector() ?>-shipping-differ" name="<?php echo $this->getKlarnaSelector() ?>-shipping-differ" class=""
                       type="hidden" value="1"/>
            <?php } ?>

            <p class="required" style="float:right;">* Verplicht</p>
        </fieldset>
        <?php
    }
}


