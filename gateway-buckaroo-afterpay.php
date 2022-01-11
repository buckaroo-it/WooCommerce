<?php


require_once dirname(__FILE__) . '/library/api/paymentmethods/afterpay/afterpay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Afterpay extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooAfterPay::class;
    public $type;
    public $b2b;
    public $vattype;
    public $country;
    public function __construct()
    {
        $this->id                     = 'buckaroo_afterpay';
        $this->title                  = 'AfterPay';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo AfterPay Old';
        $this->setIcon('24x24/afterpay.jpg', 'new/AfterPay.png');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->afterpaypayauthorize = $this->get_option('afterpaypayauthorize', 'Pay');
        $this->type       = $this->get_option('service');
        $this->b2b        = $this->get_option('enable_bb');
        $this->vattype    = $this->get_option('vattype');
    }
    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $action = ucfirst(isset($this->afterpaypayauthorize) ? $this->afterpaypayauthorize : 'pay');
        return $this->process_refund_common($action, $order_id, $amount, $reason);
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_partial_refunds($order_id, $amount = null, $reason = '', $line_item_qtys = null, $line_item_totals = null, $line_item_tax_totals = null, $originalTransactionKey = null)
    {
        $order = wc_get_order($order_id);

        if (!$this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }

        update_post_meta($order_id, '_pushallowed', 'busy');

        /** @var BuckarooAfterPay */
        $afterpay = $this->createCreditRequest($order, $amount, $reason);
        $afterpay->channel   = BuckarooConfig::CHANNEL_BACKOFFICE;
        
        if ($originalTransactionKey !== null) {
            $afterpay->OriginalTransactionKey = $originalTransactionKey;
        }

        // add items to refund call for afterpay
        $issuer = get_post_meta($order_id, '_wc_order_payment_issuer', true);

        $products         = array();
        $items            = $order->get_items();
        $itemsTotalAmount = 0;

        if ($line_item_qtys === null) {
            $line_item_qtys = json_decode(stripslashes($_POST['line_item_qtys']), true);
        }

        if ($line_item_totals === null) {
            $line_item_totals = json_decode(stripslashes($_POST['line_item_totals']), true);
        }

        if ($line_item_tax_totals === null) {
            $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);
        }

        $orderDataForChecking = $afterpay->getOrderRefundData();

        foreach ($items as $item) {
            if (isset($line_item_qtys[$item->get_id()]) && $line_item_qtys[$item->get_id()] > 0) {
                $product   = new WC_Product($item['product_id']);
                $tax_class = $product->get_attribute("vat_category");
                if (empty($tax_class)) {
                    $tax_class = $this->vattype;
                }
                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"]          = $item['product_id'];
                $tmp["ArticleQuantity"]    = $line_item_qtys[$item->get_id()];
                $tmp["ArticleUnitprice"]   = number_format(number_format($item["line_total"] + $item["line_tax"], 4) / $item["qty"], 2);
                $itemsTotalAmount += $tmp["ArticleUnitprice"] * $line_item_qtys[$item->get_id()];
                $tmp["ArticleVatcategory"] = $tax_class;
                $products[]                = $tmp;
            }
        }
        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {
            if (!empty($line_item_totals[$key])) {
                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"] = $key;
                $tmp["ArticleQuantity"] = 1;
                $tmp["ArticleUnitprice"] = number_format(($item["line_total"] + $item["line_tax"]), 2);
                $itemsTotalAmount += $tmp["ArticleUnitprice"];
                $tmp["ArticleVatcategory"] = '4';
                $products[] = $tmp;
            }
        }

        // Add shippingCosts
        $shipping_item = $order->get_items('shipping');

        $shippingCosts = 0;
        foreach ($shipping_item as $item) {
            if (isset($line_item_totals[$item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                $shippingCosts = $line_item_totals[$item->get_id()] + (isset($line_item_tax_totals[$item->get_id()]) ? current($line_item_tax_totals[$item->get_id()]) : 0);
            }
        }

        if ($shippingCosts > 0) {
            // Add virtual shipping cost product
            $tmp["ArticleDescription"] = "Shipping";
            $tmp["ArticleId"]          = BuckarooConfig::SHIPPING_SKU;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = $shippingCosts;
            $tmp["ArticleVatcategory"] = 1;
            $products[]                = $tmp;
            $itemsTotalAmount += $shippingCosts;
        }

        // end add items
        if (isset($_POST['refund_amount']) && $itemsTotalAmount == 0) {
            $afterpay->amountCredit = $_POST['refund_amount'];
        } else {
            $amount                 = $itemsTotalAmount;
            $afterpay->amountCredit = $amount;
        }

        if (!(count($products) > 0)) {
            return true;
        }

        try {
            $afterpay->checkRefundData($orderDataForChecking);
            $response = $afterpay->AfterPayRefund($products, $issuer);
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
        }

        $final_response = fn_buckaroo_process_refund($response, $order, $amount, $this->currency);

        if ($final_response === true) {
            // Store the transaction_key together with refunded products, we need this for later refunding actions
            $refund_data = json_encode(['OriginalTransactionKey' => $response->transactions, 'OriginalCaptureTransactionKey' => $afterpay->OriginalTransactionKey, 'products' => $products]);
            add_post_meta($order_id, 'buckaroo_refund', $refund_data, false);
        }

        return $final_response;
    }

    public function process_capture()
    {
        $order_id = $_POST['order_id'];

        $previous_captures = get_post_meta($order_id, '_wc_order_captures') ? get_post_meta($order_id, '_wc_order_captures') : false;

        $order = getWCOrder($order_id);
        /** @var BuckarooAfterPay */
        $afterpay = $this->createDebitRequest($order);

        $afterpay->amountDedit            = str_replace(',', '.', $_POST['capture_amount']);
        $afterpay->OriginalTransactionKey = $order->get_transaction_id();
        $afterpay->invoiceId              = (string) getUniqInvoiceId($order->get_order_number()) . (is_array($previous_captures) ? '-' . count($previous_captures) : "");

        if (!isset($customVars)) {
            $customVars = null;
        }

        // add items to capture call for afterpay
        $customVars['payment_issuer'] = get_post_meta($order_id, '_wc_order_payment_issuer', true);

        $products         = array();
        $items            = $order->get_items();
        $itemsTotalAmount = 0;

        $line_item_qtys       = json_decode(stripslashes($_POST['line_item_qtys']), true);
        $line_item_totals     = json_decode(stripslashes($_POST['line_item_totals']), true);
        $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);

        foreach ($items as $item) {
            if (isset($line_item_qtys[$item->get_id()]) && $line_item_qtys[$item->get_id()] > 0) {
                $product   = new WC_Product($item['product_id']);
                $tax_class = $product->get_attribute("vat_category");
                if (empty($tax_class)) {
                    $tax_class = $this->vattype;
                }
                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"]          = $item['product_id'];
                $tmp["ArticleQuantity"]    = $line_item_qtys[$item->get_id()];
                $tmp["ArticleUnitprice"]   = number_format(number_format($item["line_total"] + $item["line_tax"], 4) / $item["qty"], 2);
                $itemsTotalAmount += $tmp["ArticleUnitprice"] * $item["qty"];
                $tmp["ArticleVatcategory"] = $tax_class;
                $products[]                = $tmp;
            }
        }

        if (!$previous_captures) {
            $fees = $order->get_fees();
            foreach ($fees as $key => $item) {
                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"] = $key;
                $tmp["ArticleQuantity"] = 1;
                $tmp["ArticleUnitprice"] = number_format(($item["line_total"] + $item["line_tax"]), 2);
                $itemsTotalAmount += $tmp["ArticleUnitprice"];
                $tmp["ArticleVatcategory"] = '4';
                $products[] = $tmp;
            }
        }

        // Add shippingCosts
        $shipping_item = $order->get_items('shipping');

        $shippingCosts = 0;
        foreach ($shipping_item as $item) {
            if (isset($line_item_totals[$item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                $shippingCosts = $line_item_totals[$item->get_id()] + (isset($line_item_tax_totals[$item->get_id()]) ? current($line_item_tax_totals[$item->get_id()]) : 0);
            }
        }

        if ($shippingCosts > 0) {
            // Add virtual shipping cost product
            $tmp["ArticleDescription"] = "Shipping";
            $tmp["ArticleId"]          = BuckarooConfig::SHIPPING_SKU;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = $shippingCosts;
            $tmp["ArticleVatcategory"] = 1;
            $products[]                = $tmp;
        }

        // Merge products with same SKU
        $mergedProducts = array();
        foreach ($products as $product) {
            if (!isset($mergedProducts[$product['ArticleId']])) {
                $mergedProducts[$product['ArticleId']] = $product;
            } else {
                $mergedProducts[$product['ArticleId']]["ArticleQuantity"] += 1;
            }
        }

        $products = $mergedProducts;
        //  end add items

        $response         = $afterpay->Capture($customVars, $products);
        $process_response = fn_buckaroo_process_capture($response, $order, $this->currency, $products);

        return $process_response;
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
     */
    public function validate_fields()
    {
        if (empty($_POST["buckaroo-afterpay-accept"])) {
            wc_add_notice(__("Please accept licence agreements", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if (!empty($_POST["buckaroo-afterpay-b2b"]) && $_POST["buckaroo-afterpay-b2b"] == 'ON') {
            if (empty($_POST["buckaroo-afterpay-CompanyCOCRegistration"])) {
                wc_add_notice(__("Company registration number is required (KvK)", 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if (empty($_POST["buckaroo-afterpay-CompanyName"])) {
                wc_add_notice(__("Company name is required", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            $birthdate = $this->parseDate($_POST['buckaroo-afterpay-birthdate']);
            if (!$this->validateDate($birthdate, 'd-m-Y')) {
                wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }
        if (empty($_POST['buckaroo-afterpay-phone']) && empty($_POST['billing_phone'])) {
            wc_add_notice(__("Please enter phone number", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if ($this->type == 'afterpayacceptgiro') {
            if (empty($_POST["buckaroo-afterpay-CustomerAccountNumber"])) {
                wc_add_notice(__("IBAN is required", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        parent::validate_fields();
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $order = getWCOrder($order_id);
        $this->setOrderCapture($order_id, 'Afterpay');
        /** @var BuckarooAfterPay */
        $afterpay = $this->createDebitRequest($order);
        $afterpay->setType($this->type);

        $woocommerce = getWooCommerceObject();

        $afterpay->BillingGender = $_POST['buckaroo-afterpay-gender'];

        $get_billing_first_name = getWCOrderDetails($order_id, "billing_first_name");
        $get_billing_last_name  = getWCOrderDetails($order_id, "billing_last_name");
        $get_billing_email      = getWCOrderDetails($order_id, "billing_email");

        $afterpay->BillingInitials = $this->getInitials($get_billing_first_name);
        $afterpay->BillingLastName = $get_billing_last_name;
        $birthdate                 = $this->parseDate($_POST['buckaroo-afterpay-birthdate']);
        if (!empty($_POST["buckaroo-afterpay-b2b"]) && $_POST["buckaroo-afterpay-b2b"] == 'ON') {
            $birthdate = '01-01-1990';
        }
        if ($this->validateDate($birthdate, 'd-m-Y')) {
            $birthdate = date('Y-m-d', strtotime($birthdate));
        } else {
            wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }
        if (empty($_POST["buckaroo-afterpay-accept"])) {
            wc_add_notice(__("Please accept licence agreements", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }
        $shippingCosts    = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $afterpay->ShippingCosts = number_format($shippingCosts, 2) + number_format($shippingCostsTax, 2);
        }
        if (!empty($_POST["buckaroo-afterpay-b2b"]) && $_POST["buckaroo-afterpay-b2b"] == 'ON') {
            if (empty($_POST["buckaroo-afterpay-CompanyCOCRegistration"])) {
                wc_add_notice(__("Company registration number is required (KvK)", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            if (empty($_POST["buckaroo-afterpay-CompanyName"])) {
                wc_add_notice(__("Company name is required", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            $afterpay->B2B                    = 'TRUE';
            $afterpay->CompanyCOCRegistration = $_POST["buckaroo-afterpay-CompanyCOCRegistration"];
            $afterpay->CompanyName            = $_POST["buckaroo-afterpay-CompanyName"];
        }
        $afterpay->BillingBirthDate = date('Y-m-d', strtotime($birthdate));

        $get_billing_address_1              = getWCOrderDetails($order_id, 'billing_address_1');
        $get_billing_address_2              = getWCOrderDetails($order_id, 'billing_address_2');
        $address_components                 = fn_buckaroo_get_address_components($get_billing_address_1 . " " . $get_billing_address_2);
        $afterpay->BillingStreet            = $address_components['street'];
        $afterpay->BillingHouseNumber       = $address_components['house_number'];
        $afterpay->BillingHouseNumberSuffix = $address_components['number_addition'];
        $afterpay->BillingPostalCode        = getWCOrderDetails($order_id, 'billing_postcode');
        $afterpay->BillingCity              = getWCOrderDetails($order_id, 'billing_city');
        $afterpay->BillingCountry           = getWCOrderDetails($order_id, 'billing_country');
        $get_billing_email                  = getWCOrderDetails($order_id, 'billing_email');
        $afterpay->BillingEmail             = !empty($get_billing_email) ? $get_billing_email : '';
        $afterpay->BillingLanguage          = 'nl';
        $get_billing_phone                  = getWCOrderDetails($order_id, 'billing_phone');
        $number                             = $this->cleanup_phone($get_billing_phone);
        $afterpay->BillingPhoneNumber       = !empty($number['phone']) ? $number['phone'] : $_POST["buckaroo-afterpay-phone"];

        $country = null;
        if (!empty($woocommerce->customer)) {
            $country = get_user_meta($woocommerce->customer->get_id(), 'shipping_country', true);
        }

        $afterpay->AddressesDiffer = 'FALSE';
        if (isset($_POST["buckaroo-afterpay-shipping-differ"])) {
            $afterpay->AddressesDiffer = 'TRUE';

            $get_shipping_first_name             = getWCOrderDetails($order_id, 'shipping_first_name');
            $afterpay->ShippingInitials          = $this->getInitials($get_shipping_first_name);
            $get_shipping_last_name              = getWCOrderDetails($order_id, 'shipping_last_name');
            $afterpay->ShippingLastName          = $get_shipping_last_name;
            $get_shipping_address_1              = getWCOrderDetails($order_id, 'shipping_address_1');
            $get_shipping_address_2              = getWCOrderDetails($order_id, 'shipping_address_2');
            $address_components                  = fn_buckaroo_get_address_components($get_shipping_address_1 . " " . $get_shipping_address_2);
            $afterpay->ShippingStreet            = $address_components['street'];
            $afterpay->ShippingHouseNumber       = $address_components['house_number'];
            $afterpay->ShippingHouseNumberSuffix = $address_components['number_addition'];

            $afterpay->ShippingPostalCode  = getWCOrderDetails($order_id, 'shipping_postcode');
            $afterpay->ShippingCity        = getWCOrderDetails($order_id, 'shipping_city');
            $afterpay->ShippingCountryCode = getWCOrderDetails($order_id, 'shipping_country');

            $get_shipping_email            = getWCOrderDetails($order_id, 'billing_email');
            $afterpay->ShippingEmail       = !empty($get_shipping_email) ? $get_shipping_email : '';
            $afterpay->ShippingLanguage    = 'nl';
            $get_shipping_phone            = getWCOrderDetails($order_id, 'billing_phone');
            $number                        = $this->cleanup_phone($get_shipping_phone);
            $afterpay->ShippingPhoneNumber = $number['phone'];
        }
        if ($this->type == 'afterpayacceptgiro') {
            if (empty($_POST["buckaroo-afterpay-CustomerAccountNumber"])) {
                wc_add_notice(__("IBAN is required", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            $afterpay->CustomerAccountNumber = $_POST["buckaroo-afterpay-CustomerAccountNumber"];
        }

        $this->handleThirdPartyShippings($afterpay, $order, $this->country);

        $afterpay->CustomerIPAddress = getClientIpBuckaroo();
        $afterpay->Accept            = 'TRUE';
        $products                    = array();
        $items                       = $order->get_items();
        $itemsTotalAmount            = 0;

        foreach ($items as $item) {
            $product   = new WC_Product($item['product_id']);
            $tax_class = $product->get_attribute("vat_category");
            if (empty($tax_class)) {
                $tax_class = $this->vattype;
            }
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $item['product_id'];
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = number_format(number_format($item["line_total"] + $item["line_tax"], 4) / $item["qty"], 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"] * $item["qty"];
            $tmp["ArticleVatcategory"] = $tax_class;
            for ($i = 0; $item["qty"] > $i; $i++) {
                $products[] = $tmp;
            }
        }
        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $key;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = number_format(($item["line_total"] + $item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $tmp["ArticleVatcategory"] = '4';
            $products[]                = $tmp;
        }
        if (!empty($afterpay->ShippingCosts)) {
            $itemsTotalAmount += $afterpay->ShippingCosts;
        }
        if ($afterpay->amountDedit != $itemsTotalAmount) {
            if (number_format($afterpay->amountDedit - $itemsTotalAmount, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($afterpay->amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"] = 4;
                $products[]                = $tmp;
                $itemsTotalAmount += 0.01;
            } elseif (number_format($itemsTotalAmount - $afterpay->amountDedit, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($afterpay->amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"] = 4;
                $products[]                = $tmp;
                $itemsTotalAmount -= 0.01;
            }
        }

        $afterpay->returnUrl = $this->notify_url;

       
        $action = ucfirst(isset($this->afterpaypayauthorize) ? $this->afterpaypayauthorize : 'pay');

        if ($action == 'Authorize') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
        }

        $response = $afterpay->PayOrAuthorizeAfterpay($products, $action);

        // Save the original tranaction ID from the authorize or capture, we need it to do the refund
        // JM REMOVE???
        //update_post_meta( $order->get_id(), '_wc_order_payment_original_transaction_key', $this->type);

        return fn_buckaroo_process_response($this, $response, $this->mode);
    }

    /**
     * Check response data
     *
     * @access public
     */
    public function response_handler()
    {
        fn_buckaroo_process_response($this);
        exit;
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        
        $this->form_fields['service'] = [
            'title'       => __('Select afterpay service', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Please select the service', 'wc-buckaroo-bpe-gateway'),
            'options'     => ['afterpayacceptgiro' => __('Offer customer to pay afterwards by SEPA Direct Debit.', 'wc-buckaroo-bpe-gateway'), 'afterpaydigiaccept' => __('Offer customer to pay afterwards by digital invoice.', 'wc-buckaroo-bpe-gateway')],
            'default'     => 'afterpaydigiaccept'];

        $this->form_fields['enable_bb'] = [
            'title'       => __('Enable B2B option for AfterPay', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Enables or disables possibility to pay using company credentials', 'wc-buckaroo-bpe-gateway'),
            'options'     => ['enable' => 'Enable', 'disable' => 'Disable'],
            'default'     => 'disable'];

        $this->form_fields['vattype'] = [
            'title'       => __('Default product Vat type', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Please select default vat type for your products', 'wc-buckaroo-bpe-gateway'),
            'options'     => [
                '1' => '1 = High rate',
                '2' => '2 = Low rate',
                '3' => '3 = Zero rate',
                '4' => '4 = Null rate',
                '5' => '5 = middle rate'],
            'default'     => '1'];

        $this->form_fields['afterpaypayauthorize'] = [
            'title'       => __('AfterPay Pay or Capture', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway'),
            'options'     => ['pay' => 'Pay', 'authorize' => 'Authorize'],
            'default'     => 'pay'];
    }
}
