<?php


require_once dirname(__FILE__) . '/library/api/paymentmethods/afterpaynew/afterpaynew.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Afterpaynew extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooAfterPayNew::class;
    public $type;
    public $b2b;
    public $vattype;
    public $country;
    public $sendimageinfo;

    public function __construct()
    {
        $this->id                     = 'buckaroo_afterpaynew';
        $this->title                  = 'AfterPay';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo AfterPay New';
        $this->setIcon('24x24/afterpaynew.png', 'new/AfterPay.png');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->afterpaynewpayauthorize = $this->get_option('afterpaynewpayauthorize');
        $this->sendimageinfo = $this->get_option('sendimageinfo');
        $this->vattype    = $this->get_option('vattype');
        $this->type       = 'afterpay';
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
        $action = ucfirst(isset($this->afterpaynewpayauthorize) ? $this->afterpaynewpayauthorize : 'pay');
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

        /** @var BuckarooAfterPayNew */
        $afterpay = $this->createCreditRequest($order, $amount, $reason);

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

        $orderDataForChecking = $afterpay->getOrderRefundData($order);

        foreach ($items as $item) {
            if (isset($line_item_qtys[$item->get_id()]) && $line_item_qtys[$item->get_id()] > 0) {
                $product = new WC_Product($item['product_id']);

                $tax      = new WC_Tax();
                $taxes    = $tax->get_rates($product->get_tax_class());
                $rates    = array_shift($taxes);
                $itemRate = number_format(array_shift($rates), 2);

                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"]          = $item['product_id'];
                $tmp["ArticleQuantity"]    = $line_item_qtys[$item->get_id()];
                $tmp["ArticleUnitprice"]   = number_format(number_format($item["line_total"] + $item["line_tax"], 4, '.', '') / $item["qty"], 2, '.', '');
                $itemsTotalAmount += number_format($tmp["ArticleUnitprice"] * $line_item_qtys[$item->get_id()], 2, '.', '');
                $tmp["ArticleVatcategory"] = $itemRate;
                $products[]                = $tmp;
            } else if (!empty($orderDataForChecking[$item->get_id()]['tax'])) {
                $product = new WC_Product($item['product_id']);
                $tax     = new WC_Tax();
                $taxes   = $tax->get_rates($product->get_tax_class());
                $taxId   = 3; // Standard tax
                foreach ($taxes as $taxIdItem => $taxItem) {
                    $taxId = $taxIdItem;
                }

                $rates    = array_shift($taxes);
                $itemRate = number_format(array_shift($rates), 2);

                $tmp["ArticleDescription"] = $rates['label'];
                $tmp["ArticleId"]          = $taxId;
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($orderDataForChecking[$item->get_id()]['tax'], 2, '.', '');

                $itemsTotalAmount += $tmp["ArticleUnitprice"];

                $tmp["ArticleVatcategory"] = $itemRate;
                $products[]                = $tmp;
            }
        }

        $fees = $order->get_fees();

        foreach ($fees as $key => $item) {
            if (!empty($line_item_totals[$key])) {
                $feeTaxRate = $this->getFeeTax($fees[$key]);

                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"]          = $key;
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format(($item["line_total"] + $item["line_tax"]), 2);
                $tmp["ArticleVatcategory"] = $feeTaxRate;
                $products[]                = $tmp;
                $itemsTotalAmount += $tmp["ArticleUnitprice"];
            }
        }

        // Add shippingCosts
        $shippingInfo = $this->getAfterPayShippingInfo('afterpay-new', 'partial_refunds', $order, $line_item_totals, $line_item_tax_totals);
        if ($shippingInfo['costs'] > 0) {
            $products[] = $shippingInfo['shipping_virtual_product'];
            $itemsTotalAmount += $shippingInfo['costs'];
        }

        if ($orderDataForChecking['totalRefund'] != $itemsTotalAmount) {
            if (number_format($orderDataForChecking['totalRefund'] - $itemsTotalAmount, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($orderDataForChecking['totalRefund'] - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"] = 0;
                $products[]                = $tmp;
                $itemsTotalAmount += 0.01;
            } elseif (number_format($itemsTotalAmount - $orderDataForChecking['totalRefund'], 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($orderDataForChecking['totalRefund'] - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"] = 0;
                $products[]                = $tmp;
                $itemsTotalAmount -= 0.01;
            }
        }
        // end add items

        if (isset($_POST['refund_amount']) && $itemsTotalAmount == 0) {
            $afterpay->amountCredit = $_POST['refund_amount'];
        } else {
            $amount                 = $itemsTotalAmount;
            $afterpay->amountCredit = $amount;
        }

        if (!(count($products) > 0)) {
            return new WP_Error('error_refund_afterpay_no_products', __("To refund an AfterPay transaction you need to refund at least one product."));
        }

        try {
            $afterpay->checkRefundData($orderDataForChecking);
            $response = $afterpay->AfterPayRefund($products, $issuer);

        } catch (Exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
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

        $woocommerce          = getWooCommerceObject();

        $order = getWCOrder($order_id);
        /** @var BuckarooAfterPayNew */
        $afterpay = $this->createDebitRequest($order);
        $afterpay->amountDedit            = str_replace(',', '.', $_POST['capture_amount']);
        $afterpay->OriginalTransactionKey = $order->get_transaction_id();
        $afterpay->invoiceId              = (string) getUniqInvoiceId($woocommerce->order ? $woocommerce->order->get_order_number() : $order_id) . (is_array($previous_captures) ? '-' . count($previous_captures) : "");

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
                $product = new WC_Product($item['product_id']);

                $tax                       = new WC_Tax();
                $taxes                     = $tax->get_rates($product->get_tax_class());
                $rates                     = array_shift($taxes);
                $itemRate                  = number_format(array_shift($rates), 2);
                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"]          = $item['product_id'];
                $tmp["ArticleQuantity"]    = $line_item_qtys[$item->get_id()];
                $tmp["ArticleUnitprice"]   = (float) number_format(number_format($item["line_total"] + $item["line_tax"], 4, '.', '') / $item["qty"], 2, '.', '');
                $itemsTotalAmount += $tmp["ArticleUnitprice"] * $item["qty"];
                $tmp["ArticleVatcategory"] = $itemRate;
                $products[]                = $tmp;
            }
        }

        if (!$previous_captures) {
            $fees = $order->get_fees();
            foreach ($fees as $key => $item) {
                $feeTaxRate = $this->getFeeTax($fees[$key]);
                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"] = $key;
                $tmp["ArticleQuantity"] = 1;
                $tmp["ArticleUnitprice"] = number_format(($item["line_total"] + $item["line_tax"]), 2, '.', '');
                $itemsTotalAmount += $tmp["ArticleUnitprice"];
                $tmp["ArticleVatcategory"] = $feeTaxRate;
                $products[] = $tmp;
            }
        }

        // Add shippingCosts
        $shippingInfo = $this->getAfterPayShippingInfo('afterpay', 'capture', $order, $line_item_totals, $line_item_tax_totals);
        if ($shippingInfo['costs'] > 0) {
            $products[] = $shippingInfo['shipping_virtual_product'];
        }

        // end add items

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
        $country = isset($_POST['billing_country']) ? $_POST['billing_country'] : $this->country;

        if (empty($_POST["buckaroo-afterpaynew-accept"])) {
            wc_add_notice(__("Please accept licence agreements", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if (!empty($_POST["buckaroo-afterpaynew-b2b"]) && $_POST["buckaroo-afterpaynew-b2b"] == 'ON') {
            if (empty($_POST["buckaroo-afterpaynew-CompanyCOCRegistration"])) {
                wc_add_notice(__("Company registration number is required (KvK)", 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if (empty($_POST["buckaroo-afterpaynew-CompanyName"])) {
                wc_add_notice(__("Company name is required", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            $birthdate = $this->parseDate($_POST['buckaroo-afterpaynew-birthdate']);
            if (!$this->validateDate($birthdate, 'd-m-Y') && in_array($country, ['NL', 'BE'])) {
                wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if (empty($_POST['buckaroo-afterpaynew-phone']) && empty($_POST['billing_phone'])) {
            wc_add_notice(__("Please enter phone number", 'wc-buckaroo-bpe-gateway'), 'error');
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
        $this->setOrderCapture($order_id, 'Afterpaynew');
        $order = getWCOrder($order_id);
        /** @var BuckarooAfterPayNew */
        $afterpay = $this->createDebitRequest($order);
        $afterpay->setType($this->type);
        $afterpay->invoiceId = (string)getUniqInvoiceId(
            preg_replace('/\./', '-', $order->get_order_number())
        );

        $afterpay->BillingGender = $_POST['buckaroo-afterpaynew-gender'];

        $get_billing_first_name = getWCOrderDetails($order_id, "billing_first_name");
        $get_billing_last_name  = getWCOrderDetails($order_id, "billing_last_name");
        $get_billing_email      = getWCOrderDetails($order_id, "billing_email");

        $afterpay->BillingInitials = $this->getInitials($get_billing_first_name);
        $afterpay->BillingLastName = $get_billing_last_name;
        $birthdate                 = $this->parseDate($_POST['buckaroo-afterpaynew-birthdate']);
        if (!empty($_POST["buckaroo-afterpaynew-b2b"]) && $_POST["buckaroo-afterpaynew-b2b"] == 'ON') {
            // if is company reset birthdate
            $birthdate = '01-01-1990';
        }
        if ($this->validateDate($birthdate, 'd-m-Y')) {
            $birthdate = date('Y-m-d', strtotime($birthdate));
        } elseif (in_array(getWCOrderDetails($order_id, 'billing_country'), ['NL', 'BE'])) {
            wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }
        if (empty($_POST["buckaroo-afterpaynew-accept"])) {
            wc_add_notice(__("Please accept licence agreements", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }
        $shippingCosts    = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $afterpay->ShippingCosts = number_format($shippingCosts, 2) + number_format($shippingCostsTax, 2);
        }
        if (floatval($shippingCostsTax) > 0) {
            $afterpay->ShippingCostsTax = number_format(($shippingCostsTax * 100) / $shippingCosts);
        }

        // Set birthday if it's NL or BE
        $afterpay->BillingBirthDate = date('Y-m-d', strtotime($birthdate));

        $get_billing_address_1              = getWCOrderDetails($order_id, 'billing_address_1');
        $get_billing_address_2              = getWCOrderDetails($order_id, 'billing_address_2');
        $address_components                 = fn_buckaroo_get_address_components($get_billing_address_1 . " " . $get_billing_address_2);
        $afterpay->BillingStreet            = $address_components['street'];
        $afterpay->BillingHouseNumber       = $address_components['house_number'];
        $afterpay->BillingHouseNumberSuffix = $address_components['number_addition'] ?? null;
        $afterpay->BillingPostalCode        = getWCOrderDetails($order_id, 'billing_postcode');
        $afterpay->BillingCity              = getWCOrderDetails($order_id, 'billing_city');
        $afterpay->BillingCountry           = getWCOrderDetails($order_id, 'billing_country');
        $get_billing_email                  = getWCOrderDetails($order_id, 'billing_email');
        $afterpay->BillingEmail             = !empty($get_billing_email) ? $get_billing_email : '';
        $afterpay->BillingLanguage          = 'nl';
        $get_billing_phone                  = getWCOrderDetails($order_id, 'billing_phone');
        $number                             = $this->cleanup_phone($get_billing_phone);
        $afterpay->BillingPhoneNumber       = !empty($number['phone']) ? $number['phone'] : $_POST["buckaroo-afterpaynew-phone"];

        $afterpay->AddressesDiffer = 'FALSE';
        if (isset($_POST["buckaroo-afterpaynew-shipping-differ"])) {
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

        $this->handleThirdPartyShippings($afterpay, $order, $this->country);

        $afterpay->CustomerIPAddress = getClientIpBuckaroo();
        $afterpay->Accept            = 'TRUE';
        $products = $this->getProductsInfo($order, $afterpay->amountDedit, $afterpay->ShippingCosts, 'afterpay-new');

        $afterpay->returnUrl = $this->notify_url;


        $action = ucfirst(isset($this->afterpaynewpayauthorize) ? $this->afterpaynewpayauthorize : 'pay');

        if ($action == 'Authorize') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
        }

        $response = $afterpay->PayOrAuthorizeAfterpay($products, $action);
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
        
        $this->form_fields['afterpaynewpayauthorize'] = array(
            'title'       => __('AfterPay Pay or Capture', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('pay' => 'Pay', 'authorize' => 'Authorize'),
            'default'     => 'pay');

        $this->form_fields['sendimageinfo'] = array(
            'title'       => __('Send image info', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Image info will be sent to BPE gateway inside ImageUrl parameter', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('0' => 'No', '1' => 'Yes'),
            'default'     => 'pay');

    }

    public function getProductImage($product) {

        if ($this->sendimageinfo){
            $src = get_the_post_thumbnail_url($product->get_id());
            if (!$src) {
                $imgTag = $product->get_image();
                $doc = new DOMDocument();
                $doc->loadHTML($imgTag);
                $xpath = new DOMXPath($doc);
                $src = $xpath->evaluate("string(//img/@src)");
            }

            if (strpos($src, '?') !== false) {
                $src = substr($src, 0, strpos($src, '?'));
            }

            if ($srcInfo = @getimagesize($src)) {
                if (!empty($srcInfo['mime']) && in_array($srcInfo['mime'], ['image/png', 'image/jpeg'])) {
                    if (!empty($srcInfo[0]) && ($srcInfo[0] >= 100) && ($srcInfo[0] <= 1280)) {
                        $imageUrl = $src;
                    }
                }
            } 
        }
        return $imageUrl; 
    }
}
