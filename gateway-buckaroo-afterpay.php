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
    public $productQtyLoop = true;
    
    public function __construct()
    {
        $this->id                     = 'buckaroo_afterpay';
        $this->title                  = 'AfterPay';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo AfterPay';
        $this->setIcon('24x24/afterpay.jpg', 'svg/AfterPay.svg');
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
            $line_item_qtys = buckaroo_request_sanitized_json('line_item_qtys');
        }

        if ($line_item_totals === null) {
            $line_item_totals = buckaroo_request_sanitized_json('line_item_totals');
        }

        if ($line_item_tax_totals === null) {
            $line_item_tax_totals = buckaroo_request_sanitized_json('line_item_tax_totals');
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
        $shippingInfo = $this->getAfterPayShippingInfo('afterpay', 'partial_refunds', $order, $line_item_totals, $line_item_tax_totals);
        if ($shippingInfo['costs'] > 0) {
            $products[] = $shippingInfo['shipping_virtual_product'];
            $itemsTotalAmount += $shippingInfo['costs'];
        }

        $ref_amount = $this->request('refund_amount');
        // end add items
        if ($ref_amount !== null && $itemsTotalAmount == 0) {
            $afterpay->amountCredit = $ref_amount;
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
        $order_id = $this->request('order_id');
        
        if ($order_id === null || !is_numeric($order_id)) {
            return $this->create_capture_error(__('A valid order number is required'));
        }

        $capture_amount = $this->request('capture_amount');
        if($capture_amount === null || !is_scalar($capture_amount)) {
            return $this->create_capture_error(__('A valid capture amount is required'));
        }

        $previous_captures = get_post_meta($order_id, '_wc_order_captures') ? get_post_meta($order_id, '_wc_order_captures') : false;

        $order = getWCOrder($order_id);
        /** @var BuckarooAfterPay */
        $afterpay = $this->createDebitRequest($order);

        $afterpay->amountDedit            = str_replace(',', '.', $capture_amount);
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
        
        $line_item_qtys         = buckaroo_request_sanitized_json('line_item_qtys');
		$line_item_totals       = buckaroo_request_sanitized_json('line_item_totals');
		$line_item_tax_totals   = buckaroo_request_sanitized_json('line_item_tax_totals');

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
        $shippingInfo = $this->getAfterPayShippingInfo('afterpay', 'capture', $order, $line_item_totals, $line_item_tax_totals);
        if ($shippingInfo['costs'] > 0) {
            $products[] = $shippingInfo['shipping_virtual_product'];
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
        if ($this->request("buckaroo-afterpay-accept") === null) {
            wc_add_notice(__("Please accept licence agreements", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        $birthdate = $this->parseDate($this->request('buckaroo-afterpay-birthdate'));
        if (!$this->validateDate($birthdate, 'd-m-Y')) {
            wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        
        if ($this->request("buckaroo-afterpay-b2b") == 'ON') {
            if ($this->request("buckaroo-afterpay-CompanyCOCRegistration") === null) {
                wc_add_notice(__("Company registration number is required (KvK)", 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if ($this->request("buckaroo-afterpay-CompanyName") === null) {
                wc_add_notice(__("Company name is required", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }
        

        if ($this->request('buckaroo-afterpay-phone') === null && $this->request('billing_phone') === null) {
            wc_add_notice(__("Please enter phone number", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if ($this->type == 'afterpayacceptgiro') {
            if ($this->request("buckaroo-afterpay-CustomerAccountNumber") === null) {
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

        
        $birthdate                 = $this->parseDate(
            $this->request('buckaroo-afterpay-birthdate')
        );
        
        $shippingCosts    = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $afterpay->ShippingCosts = number_format($shippingCosts, 2) + number_format($shippingCostsTax, 2);
        }
        if ($this->request("buckaroo-afterpay-b2b") == 'ON') {
        
            $afterpay->B2B                    = 'TRUE';
            $afterpay->CompanyCOCRegistration = $this->request("buckaroo-afterpay-CompanyCOCRegistration");
            $afterpay->CompanyName            = $this->request("buckaroo-afterpay-CompanyName");
        }

        $order_details = new Buckaroo_Order_Details($order);
        $afterpay = $this->getBillingInfo($order_details, $afterpay, $birthdate);
        $afterpay = $this->getShippingInfo($order_details, $afterpay);
        
        if ($this->type == 'afterpayacceptgiro') {
            $afterpay->CustomerAccountNumber = $this->request("buckaroo-afterpay-CustomerAccountNumber");
        }
        /** @var BuckarooAfterPay */
        $afterpay = $this->handleThirdPartyShippings($afterpay, $order, $this->country);

        $afterpay->CustomerIPAddress = getClientIpBuckaroo();
        $afterpay->Accept            = 'TRUE';
        $products = $this->getProductsInfo($order, $afterpay->amountDedit, $afterpay->ShippingCosts);

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
     * Get billing info for pay request
     *
     * @param Buckaroo_Order_Details $order_details
     * @param BuckarooAfterPay $method
     * @param string $birthdate
     *
     * @return BuckarooAfterPay  $method
     */
    protected function getBillingInfo($order_details, $method, $birthdate)
    {
        /** @var BuckarooAfterPay */
        $method = $this->set_billing($method, $order_details);
        $method->BillingInitials  = $order_details->getInitials(
            $order_details->getBilling('first_name')
        );
        $method->BillingBirthDate = date('Y-m-d', strtotime($birthdate));
        if (empty($method->BillingPhoneNumber)) {
            $method->BillingPhoneNumber =  $this->request("buckaroo-afterpay-phone");
        }
        return $method;
    }
    /**
     * Get shipping info for pay request
     *
     * @param Buckaroo_Order_Details $order_details
     * @param BuckarooAfterPay $method
     *
     * @return BuckarooAfterPay $method
     */
    protected function getShippingInfo($order_details, $method)
    {
        $method->AddressesDiffer = 'FALSE';
        if ($this->request("buckaroo-afterpay-shipping-differ") !== null) {
            $method->AddressesDiffer = 'TRUE';
            /** @var BuckarooAfterPay */
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

    /**
     * Get VAT type from settings page
     *
     * @param mixed $product
     *
     * @return string
     */
    public function getProductTaxRate($product) {
        if ($product instanceof WC_Order_Item_Product) {
            $product = new WC_Product($product->get_product_id());
        }

        $tax_class = $product->get_attribute("vat_category");
        
        if (empty($tax_class)) {
            $tax_class = $this->vattype;
        }
        
        return $tax_class;
    }

    public function getProductSpecific($product, $item, $tmp) {
        //Product
        $data['product_tmp'] = $tmp;
        $data['product_tmp']['ArticleUnitprice']   = number_format(number_format($item['line_total'] + $item['line_tax'], 4) / $item['qty'], 2);
        $data['product_tmp']['ArticleQuantity'] = 1;
        $data['product_itemsTotalAmount'] = $data['product_tmp']['ArticleUnitprice'] * $item['qty'];

        return $data;
    }
    public function getFeeSpecific($item, $tmp, $fee){
        $data['product_tmp'] = $tmp;
        $data['product_tmp']['ArticleVatcategory'] = '4';

        return $data;
    }
    public function getRemainingPriceSpecific($mode, $amountDedit, $itemsTotalAmount, $tmp) { 
        $data['product_tmp'] = $tmp;
        $data['product_tmp']['ArticleVatcategory'] =  4;

        return $data;
    }
}
