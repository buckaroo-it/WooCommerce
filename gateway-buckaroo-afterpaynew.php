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
        $this->title                  = 'Afterpay (by Buckaroo)';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo AfterPay New';
        $this->setIcon('24x24/afterpaynew.png', 'svg/AfterPay.svg');
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
            $line_item_qtys = buckaroo_request_sanitized_json('line_item_qtys');
        }
        
        
        if ($line_item_totals === null) {
            $line_item_totals = buckaroo_request_sanitized_json('line_item_totals');
        }
        
        if ($line_item_tax_totals === null) {
            $line_item_tax_totals  = buckaroo_request_sanitized_json('line_item_tax_totals');
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
                $feeTaxRate = $this->getProductTaxRate($item);

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

        $ref_amount = $this->request('refund_amount');
        if ($ref_amount !== null && $itemsTotalAmount == 0) {
            $afterpay->amountCredit = $ref_amount;
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
        $order_id = $this->request('order_id');
        
        if ($order_id === null || !is_numeric($order_id)) {
            return $this->create_capture_error(__('A valid order number is required'));
        }

        $capture_amount = $this->request('capture_amount');
        if($capture_amount === null || !is_scalar($capture_amount)) {
            return $this->create_capture_error(__('A valid capture amount is required'));
        }

        $previous_captures = get_post_meta($order_id, '_wc_order_captures') ? get_post_meta($order_id, '_wc_order_captures') : false;

        $woocommerce          = getWooCommerceObject();

        $order = getWCOrder($order_id);
        /** @var BuckarooAfterPayNew */
        $afterpay = $this->createDebitRequest($order);
        $afterpay->amountDedit            = str_replace(',', '.', $capture_amount);
        $afterpay->OriginalTransactionKey = $order->get_transaction_id();
        $afterpay->invoiceId              = (string) getUniqInvoiceId($woocommerce->order ? $woocommerce->order->get_order_number() : $order_id) . (is_array($previous_captures) ? '-' . count($previous_captures) : "");

        // add items to capture call for afterpay
        $customVars['payment_issuer'] = get_post_meta($order_id, '_wc_order_payment_issuer', true);

        $products         = array();
        $items            = $order->get_items();
        $itemsTotalAmount = 0;

        $line_item_qtys         = buckaroo_request_sanitized_json('line_item_qtys');
		$line_item_totals       = buckaroo_request_sanitized_json('line_item_totals');
		$line_item_tax_totals   = buckaroo_request_sanitized_json('line_item_tax_totals');

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
                $feeTaxRate = $this->getProductTaxRate($item);
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
        $country = $this->request('billing_country');
        if ($country === null) {
            $country =  $this->country;
        }

        $birthdate = $this->parseDate(
            $this->request('buckaroo-afterpaynew-birthdate')
        );
        if (!$this->validateDate($birthdate, 'd-m-Y') && in_array($country, ['NL', 'BE'])) {
            wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if(!in_array($this->request('buckaroo-afterpaynew-gender'), ["1","2"])) {
            wc_add_notice(__("Unknown gender", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if ($this->request("buckaroo-afterpaynew-accept") === null) {
            wc_add_notice(__("Please accept licence agreements", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        $b2b = $this->request('buckaroo-afterpaynew-b2b');
        if ($b2b == 'ON') {
            if ($this->request("buckaroo-afterpaynew-CompanyCOCRegistration") === null) {
                wc_add_notice(__("Company registration number is required (KvK)", 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if ($this->request("buckaroo-afterpaynew-CompanyName") === null) {
                wc_add_notice(__("Company name is required", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if ($this->request('buckaroo-afterpaynew-phone') === null && $this->request('billing_phone') === null) {
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
        $order_details = new Buckaroo_Order_Details($order);
     
        $birthdate        = $this->parseDate($this->request('buckaroo-afterpaynew-birthdate'));

        $shippingCosts    = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $afterpay->ShippingCosts = number_format($shippingCosts, 2) + number_format($shippingCostsTax, 2);
        }
        if (floatval($shippingCostsTax) > 0) {
            $afterpay->ShippingCostsTax = number_format(($shippingCostsTax * 100) / $shippingCosts);
        }
        $afterpay = $this->getBillingInfo($order_details, $afterpay, $birthdate);
        $afterpay = $this->getShippingInfo($order_details, $afterpay);
        
        /** @var BuckarooAfterPayNew */
        $afterpay = $this->handleThirdPartyShippings($afterpay, $order, $this->country);

        $afterpay->CustomerIPAddress = getClientIpBuckaroo();
        $afterpay->Accept            = 'TRUE';
        $products = $this->getProductsInfo($order, $afterpay->amountDedit, $afterpay->ShippingCosts);

        $afterpay->returnUrl = $this->notify_url;


        $action = ucfirst(isset($this->afterpaynewpayauthorize) ? $this->afterpaynewpayauthorize : 'pay');

        if ($action == 'Authorize') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
        }

        $response = $afterpay->PayOrAuthorizeAfterpay($products, $action);
        return fn_buckaroo_process_response($this, $response, $this->mode);
    }
    /**
     * Get billing info for pay request
     *
     * @param Buckaroo_Order_Details $order_details
     * @param BuckarooAfterPayNew $method
     * @param string $birthdate
     *
     * @return BuckarooAfterPayNew  $method
     */
    protected function getBillingInfo($order_details, $method, $birthdate)
    {
        /** @var BuckarooAfterPayNew */
        $method = $this->set_billing($method, $order_details);
        $method->BillingGender    = $this->request('buckaroo-afterpaynew-gender');
        $method->BillingInitials  = $order_details->getInitials(
            $order_details->getBilling('first_name')
        );
        $method->BillingBirthDate = date('Y-m-d', strtotime($birthdate));
        if (empty($method->BillingPhoneNumber)) {
            $method->BillingPhoneNumber =  $this->request("buckaroo-afterpaynew-phone");
        }

        return $method;
    }
    /**
     * Get shipping info for pay request
     *
     * @param Buckaroo_Order_Details $order_details
     * @param BuckarooAfterPayNew $method
     *
     * @return BuckarooAfterPayNew $method
     */
    protected function getShippingInfo($order_details, $method)
    {
        $method->AddressesDiffer = 'FALSE';
        if ($this->request("buckaroo-afterpaynew-shipping-differ") !== null) {
            $method->AddressesDiffer = 'TRUE';
            /** @var BuckarooAfterPayNew */
            $method = $this->set_shipping($method, $order_details);
            $method->ShippingInitials = $order_details->getInitials(
                $order_details->getShipping('first_name')
            );
        }
        return $method;
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
            'default'     => 'pay'
        );

        $this->form_fields['sendimageinfo'] = array(
            'title'       => __('Send image info', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Image info will be sent to BPE gateway inside ImageUrl parameter', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('0' => 'No', '1' => 'Yes'),
            'default'     => 'pay',
            'desc_tip'    => 'Product images are only shown when they are available in JPG or PNG format'
        );

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

    public function getProductSpecific($product, $item, $tmp) {
        //Product
        $data['product_tmp'] = $tmp;
        $data['product_tmp']['ArticleUnitprice'] = number_format(number_format($item['line_total'] + $item['line_tax'], 4, '.', '') / $item['qty'], 2, '.', '');
        $data['product_tmp']['ProductUrl'] = get_permalink($item['product_id']);
        $imgUrl = $this->getProductImage($product);
        //Don't sent the tag if imgurl not set
        if(!empty($imgUrl)){
            $data['product_tmp']['ImageUrl'] = $imgUrl;
        }
        
        $data['product_itemsTotalAmount'] = number_format($data['product_tmp']['ArticleUnitprice'] * $item['qty'], 2, '.', '');

        return $data;
    }

    public function getRemainingPriceSpecific($mode, $amountDedit, $itemsTotalAmount, $tmp) {
        $data['product_tmp'] = $tmp;

        if ($mode == 2) {            
            $data['product_tmp']['ArticleUnitprice'] = number_format($amountDedit - $itemsTotalAmount, 2, '.', '');
        }
        
        return $data;
    }
}
