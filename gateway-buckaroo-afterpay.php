<?php

require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/afterpay/afterpay.php');

function getClientIpBuckaroo()
{
    $ipaddress = '';
    if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (! empty($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (! empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (! empty($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } elseif (! empty($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    $ex = explode(",", $ipaddress);
    return trim($ex[0]);
}

/**
* @package Buckaroo
*/
class WC_Gateway_Buckaroo_Afterpay extends WC_Gateway_Buckaroo
{
    public $type;
    public $b2b;
    public $showpayproc;
    public $vattype;
    public function __construct()
    {
        $woocommerce = getWooCommerceObject();

        $this->id = 'buckaroo_afterpay';
        $this->title = 'AfterPay';
        $this->icon 		= apply_filters('woocommerce_buckaroo_afterpay_icon', plugins_url('library/buckaroo_images/24x24/afterpay.jpg', __FILE__));
        $this->has_fields 	= false;
        $this->method_title = 'Buckaroo AfterPay Old';
        $this->description = "Betaal met AfterPay Old";
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $this->currency = get_woocommerce_currency();
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');

        $this->secretkey = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode = BuckarooConfig::getMode();
        $this->thumbprint = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture = BuckarooConfig::get('CULTURE');
        $this->usenotification = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');

        parent::__construct();

        $this->afterpaypayauthorize = (isset($this->settings['afterpaypayauthorize']) ? $this->settings['afterpaypayauthorize'] : 'Pay');

        $this->supports           = [
            'products',
            'refunds'
        ];
        $this->type = $this->settings['service'];
        $this->b2b = $this->settings['enable_bb'];
        $this->vattype = $this->settings['vattype'];
        $this->notify_url = home_url('/');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '<')) {
        } else {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ]);
            add_action('woocommerce_api_wc_gateway_buckaroo_sepadirectdebit', [ $this, 'response_handler' ]);
            if ($this->showpayproc) {
                add_action('woocommerce_thankyou_buckaroo_afterpay', [ $this, 'thankyou_description' ]);
            }
            $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Afterpay', $this->notify_url);
        }
        //add_action( 'woocommerce_api_callback', 'response_handler' );
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
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $action = ucfirst(isset($this->afterpaypayauthorize) ? $this->afterpaypayauthorize : 'pay');

        if ($action == 'Authorize') {
            // check if order is captured
            
            $captures = get_post_meta($order_id, 'buckaroo_capture', false);
            $previous_refunds = get_post_meta($order_id, 'buckaroo_refund', false);

            if ($captures == false || count($captures) < 1) {
                return new WP_Error('error_refund_trid', __("Order is not captured yet, you can only refund captured orders"));
            }

            // Merge previous refunds with captures
            foreach ($captures as &$captureJson) {
                $capture = json_decode($captureJson, true);
                foreach ($previous_refunds as &$refundJson) {
                    $refund = json_decode($refundJson, true);

                    if (isset($refund['OriginalCaptureTransactionKey']) && $capture['OriginalTransactionKey'] == $refund['OriginalCaptureTransactionKey']) {
                        
                        foreach ($capture['products'] as &$capture_product) {
                            foreach ($refund['products'] as &$refund_product) {
                                if ($capture_product['ArticleId'] != BuckarooConfig::SHIPPING_SKU && $capture_product['ArticleId'] == $refund_product['ArticleId'] && $refund_product['ArticleQuantity'] > 0) {
                                    if ($capture_product['ArticleQuantity'] >= $refund_product['ArticleQuantity']) {
                                        $capture_product['ArticleQuantity'] -= $refund_product['ArticleQuantity'];
                                        $refund_product['ArticleQuantity'] = 0;
                                    } else {
                                        $refund_product['ArticleQuantity'] -= $capture_product['ArticleQuantity'];
                                        $capture_product['ArticleQuantity'] = 0;
                                    }
                                } else if ($capture_product['ArticleId'] == BuckarooConfig::SHIPPING_SKU && $capture_product['ArticleId'] == $refund_product['ArticleId'] && $refund_product['ArticleUnitprice'] > 0) {
                                    if ($capture_product['ArticleUnitprice'] >= $refund_product['ArticleUnitprice']) {
                                        $capture_product['ArticleUnitprice'] -= $refund_product['ArticleUnitprice'];
                                        $refund_product['ArticleUnitprice'] = 0;
                                    } else {
                                        $refund_product['ArticleUnitprice'] -= $capture_product['ArticleUnitprice'];
                                        $capture_product['ArticleUnitprice'] = 0;
                                    }
                                }
                            }
                        }
                    }
                    $refundJson = json_encode($refund);
                }  
                $captureJson = json_encode($capture);
            }

            $captures = json_decode(json_encode($captures), true);

            $line_item_qtys = json_decode(stripslashes($_POST['line_item_qtys']), true);
            $line_item_totals = json_decode(stripslashes($_POST['line_item_totals']), true);
            $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);

            $line_item_qtys_new = array();
            $line_item_totals_new = array();
            $line_item_tax_totals_new = array();
            $originalTransactionKey_new = array();
            $shippingOriginalTransactionKey_new = array();
            
            $order = wc_get_order($order_id);
            $items = $order->get_items();

            // Items to products
            $item_ids = array();

            foreach ($items as $item) {
                $item_ids[$item->get_id()] = $item->get_product_id();
            }

            $counter = 0;

            $totalQtyToRefund = 0;

            // Loop through products
            if (is_array($line_item_qtys)) {
                foreach ($line_item_qtys as $id_to_refund => $qty_to_refund) {
                    // Find free `slots` in captures
                    foreach ($captures as $captureJson) {
                        $capture = json_decode($captureJson, true);
                        foreach ($capture['products'] as $product) {
                            if ($product['ArticleId'] == $item_ids[$id_to_refund]) {
                                // Found the product in the capture.
                                // See if qty is sufficent.
                                if ($qty_to_refund > 0) {
                                    if ($qty_to_refund <= $product['ArticleQuantity']) {
                                        $line_item_qtys_new[$counter][$id_to_refund] = $qty_to_refund;
                                        $qty_to_refund = 0;
                                    } else {
                                        $line_item_qtys_new[$counter][$id_to_refund] = $product['ArticleQuantity'];
                                        $qty_to_refund -= $product['ArticleQuantity'];                                        
                                    }
                                    $originalTransactionKey_new[$counter] = $capture['OriginalTransactionKey'];
                                    $counter++;
                                }

                            }
                        }
                    }
                    $totalQtyToRefund+= $qty_to_refund;
                }
            }

            $counter = 0;

            // loop for shipping costs
            $shipping_item = $order->get_items('shipping');

            $shippingCostsToRefund = 0;
            foreach ($shipping_item as $item) {
                if (isset($line_item_totals[$item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                    $shippingCostsToRefund = $line_item_totals[$item->get_id()] + (isset($line_item_tax_totals[$item->get_id()]) ? current($line_item_tax_totals[$item->get_id()]) : 0);
                    $shippingIdToRefund = $item->get_id();
                }
            }

            // Find free `slots` in captures
            foreach ($captures as $captureJson) {                                
                $capture = json_decode($captureJson, true);
                foreach ($capture['products'] as $product) {
                    if ($product['ArticleId'] == BuckarooConfig::SHIPPING_SKU) {
                        // Found the shipping in the capture.
                        // See if amount is sufficent.
                        if ($shippingCostsToRefund > 0) {
                            if ($shippingCostsToRefund <= $product['ArticleUnitprice']) {
                                $line_item_totals_new[$counter][$shippingIdToRefund] = $shippingCostsToRefund;
                                $line_item_tax_totals_new[$counter][$shippingIdToRefund] = array(1 => 0);
                                $shippingCostsToRefund = 0;
                            } else {
                                $line_item_totals_new[$counter][$shippingIdToRefund] = $product['ArticleUnitprice'];
                                $line_item_tax_totals_new[$counter][$shippingIdToRefund] = array(1 => 0);
                                $shippingCostsToRefund -= $product['ArticleUnitprice'];                                        
                            }
                            $shippingOriginalTransactionKey_new[$counter] = $capture['OriginalTransactionKey'];
                            $counter++;
                        }

                    }
                }

            }

            // Check if something cannot be refunded
            $NotRefundable = false;

            if ($shippingCostsToRefund > 0 || $totalQtyToRefund > 0) {
                $NotRefundable = true;
            }

            if ($NotRefundable) {
                return new WP_Error('error_refund_trid', __("Selected items or amount is not fully captured, you can only refund captured items"));
            }

            // Process all refund items
            $refund_result = array();
            for ($i=0; $i<count($line_item_qtys_new); $i++) {
                if ($amount > 0) {
                    $refund_result[] = $this->process_partial_refunds(
                    $order_id,
                    $amount,
                    $reason,
                    $line_item_qtys_new[$i],
                    [],
                    [],
                    $originalTransactionKey_new[$i]
                );
                }
            }

            // Process all refund shipping
            for ($i=0; $i<count($line_item_totals_new); $i++) {
                if ($amount > 0) {
                    $refund_result[] = $this->process_partial_refunds(
                    $order_id,
                    $amount,
                    $reason,
                    [],
                    $line_item_totals_new[$i],
                    $line_item_tax_totals_new[$i],
                    $shippingOriginalTransactionKey_new[$i]
                );
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
    public function process_partial_refunds($order_id, $amount = null, $reason = '', $line_item_qtys = null, $line_item_totals = null, $line_item_tax_totals = null, $originalTransactionKey = null)
    {
        $order = wc_get_order($order_id);

        if (! $this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }

        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order = wc_get_order($order_id);
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }
        $afterpay = new BuckarooAfterPay($this->type);
        $afterpay->amountDedit = 0;
        $afterpay->currency = $this->currency;
        $afterpay->description = $reason;
        if ($this->mode=='test') {
            $afterpay->invoiceId = 'WP_'.(string)$order_id;
        }
        $afterpay->orderId = $order_id;
        if ($originalTransactionKey === null) {
            $afterpay->OriginalTransactionKey = $order->get_transaction_id();
        } else {
            $afterpay->OriginalTransactionKey = $originalTransactionKey;
        }
        $afterpay->returnUrl = $this->notify_url;
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $afterpay->channel = BuckarooConfig::getChannel($payment_type, 'process_refund');

        // add items to refund call for afterpay
        $issuer = get_post_meta($order_id, '_wc_order_payment_issuer', true);

        $products = array();
        $items = $order->get_items();
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
                $product = new WC_Product($item['product_id']);
                $tax_class = $product->get_attribute("vat_category");
                if (empty($tax_class)) {
                    $tax_class = $this->vattype;
                    //wc_add_notice( __("Vat category (vat_category) do not exist for product ", 'wc-buckaroo-bpe-gateway').$item['name'], 'error' );
                    // return;
                }
                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"] = $item['product_id'];
                $tmp["ArticleQuantity"] = $line_item_qtys[$item->get_id()];
                $tmp["ArticleUnitprice"] = number_format(number_format($item["line_total"]+$item["line_tax"], 4)/$item["qty"], 2);
                $itemsTotalAmount += $tmp["ArticleUnitprice"] * $line_item_qtys[$item->get_id()];
                $tmp["ArticleVatcategory"] = $tax_class;
                $products[] = $tmp;
            }
        }
        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"] = $key;
            $tmp["ArticleQuantity"] = 1;
            $tmp["ArticleUnitprice"] = number_format(($item["line_total"]+$item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $tmp["ArticleVatcategory"] = '4';
            $products[] = $tmp;
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
            $tmp["ArticleId"] = BuckarooConfig::SHIPPING_SKU;
            $tmp["ArticleQuantity"] = 1;
            $tmp["ArticleUnitprice"] = $shippingCosts;
            $tmp["ArticleVatcategory"] = 1;
            $products[] = $tmp;
            $itemsTotalAmount += $shippingCosts;
        }

        // end add items

        if( isset($_POST['refund_amount']) && $itemsTotalAmount == 0 ){
            $afterpay->amountCredit = $_POST['refund_amount'];
        }
        else{
            $amount = $itemsTotalAmount;
            $afterpay->amountCredit = $amount;
        }

        if(!(count($products) > 0)){
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

        $woocommerce = getWooCommerceObject();
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $afterpay = new BuckarooAfterPay();
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }

        $order = getWCOrder($order_id);

        $afterpay->amountDedit = $_POST['capture_amount'];
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $afterpay->OriginalTransactionKey = $order->get_transaction_id();
        $afterpay->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $afterpay->currency = $this->currency;
        $afterpay->description = $this->transactiondescription;
        $afterpay->invoiceId = (string)getUniqInvoiceId($order_id) . (is_array($previous_captures) ? '-' . count($previous_captures) : "");
        $afterpay->orderId = (string)$order_id;
        $afterpay->returnUrl = $this->notify_url;

        if (! isset($customVars)) {
            $customVars = null;
        }

        // add items to capture call for afterpay
        $customVars['payment_issuer'] = get_post_meta($order_id, '_wc_order_payment_issuer', true);
        
        $products = array();
        $items = $order->get_items();
        $itemsTotalAmount = 0;

        $line_item_qtys = json_decode(stripslashes($_POST['line_item_qtys']), true);
        $line_item_totals = json_decode(stripslashes($_POST['line_item_totals']), true);
        $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);

        foreach ($items as $item) {
            if (isset($line_item_qtys[$item->get_id()]) && $line_item_qtys[$item->get_id()] > 0) {
                $product = new WC_Product($item['product_id']);
                $tax_class = $product->get_attribute("vat_category");
                if (empty($tax_class)) {
                    $tax_class = $this->vattype;
                }
                $tmp["ArticleDescription"] = $item['name'];
                $tmp["ArticleId"] = $item['product_id'];
                $tmp["ArticleQuantity"] = $line_item_qtys[$item->get_id()];
                $tmp["ArticleUnitprice"] = number_format(number_format($item["line_total"]+$item["line_tax"], 4)/$item["qty"], 2);
                $itemsTotalAmount += $tmp["ArticleUnitprice"] * $item["qty"];
                $tmp["ArticleVatcategory"] = $tax_class;
//                for ($i = 0 ; $item["qty"] > $i && $line_item_qtys[$item->get_id()] > $i ; $i++) {
                $products[] = $tmp;
//                }
            }
        }
        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"] = $key;
            $tmp["ArticleQuantity"] = 1;
            $tmp["ArticleUnitprice"] = number_format(($item["line_total"]+$item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $tmp["ArticleVatcategory"] = '4';
            $products[] = $tmp;
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
            $tmp["ArticleId"] = BuckarooConfig::SHIPPING_SKU;
            $tmp["ArticleQuantity"] = 1;
            $tmp["ArticleUnitprice"] = $shippingCosts;
            $tmp["ArticleVatcategory"] = 1;
            $products[] = $tmp;
        }

        // Merge products with same SKU 

        $mergedProducts = array();
        foreach ($products as $product) {
            if (! isset($mergedProducts[$product['ArticleId']])) {
                $mergedProducts[$product['ArticleId']] = $product;
            } else {
                $mergedProducts[$product['ArticleId']]["ArticleQuantity"] += 1;
            }
        }

        $products = $mergedProducts;
        //  end add items

        $response = $afterpay->Capture($customVars, $products);
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
        if (! empty($_POST["buckaroo-afterpay-b2b"]) && $_POST["buckaroo-afterpay-b2b"] == 'ON') {
            if (empty($_POST["buckaroo-afterpay-CompanyCOCRegistration"])) {
                wc_add_notice(__("Company registration number is required (KvK)", 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if (empty($_POST["buckaroo-afterpay-CompanyName"])) {
                wc_add_notice(__("Company name is required", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            $birthdate = $_POST['buckaroo-afterpay-birthdate'];
            if (! $this->validateDate($birthdate, 'd-m-Y')) {
                wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }
        if ($this->type == 'afterpayacceptgiro') {
            if (empty($_POST["buckaroo-afterpay-CustomerAccountNumber"])) {
                wc_add_notice(__("IBAN is required", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if (version_compare(WC()->version, '3.6', '<')) {
            resetOrder();
        }
        
        return;
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {

        // Save this meta that is used later for the Capture and refund call
        update_post_meta($order_id, '_wc_order_selected_payment_method', 'Afterpay');
        update_post_meta($order_id, '_wc_order_payment_issuer', $this->type);

        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order = new WC_Order($order_id);
        $afterpay = new BuckarooAfterPay($this->type);
        if (checkForSequentialNumbersPlugin()) {
            $order_id = $order->get_order_number(); //Use sequential id
        }
        if (method_exists($order, 'get_order_total')) {
            $afterpay->amountDedit = $order->get_order_total();
        } else {
            $afterpay->amountDedit = $order->get_total();
        }
        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));
        $afterpay->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $afterpay->currency = $this->currency;
        $afterpay->description = $this->transactiondescription;
        $afterpay->invoiceId = getUniqInvoiceId((string)$order_id, $this->mode);
        $afterpay->orderId = (string)$order_id;
        
        $afterpay->BillingGender = $_POST['buckaroo-afterpay-gender'];

        $get_billing_first_name = getWCOrderDetails($order_id, "billing_first_name");
        $get_billing_last_name = getWCOrderDetails($order_id, "billing_last_name");
        $get_billing_email = getWCOrderDetails($order_id, "billing_email");

        $afterpay->BillingInitials = $this->getInitials($get_billing_first_name);
        $afterpay->BillingLastName = $get_billing_last_name;
        $birthdate = $_POST['buckaroo-afterpay-birthdate'];
        if (! empty($_POST["buckaroo-afterpay-b2b"]) && $_POST["buckaroo-afterpay-b2b"] == 'ON') {
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
        $shippingCosts = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $afterpay->ShippingCosts = number_format($shippingCosts, 2)+number_format($shippingCostsTax, 2);
        }
        if (! empty($_POST["buckaroo-afterpay-b2b"]) && $_POST["buckaroo-afterpay-b2b"] == 'ON') {
            if (empty($_POST["buckaroo-afterpay-CompanyCOCRegistration"])) {
                wc_add_notice(__("Company registration number is required (KvK)", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            if (empty($_POST["buckaroo-afterpay-CompanyName"])) {
                wc_add_notice(__("Company name is required", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            $afterpay->B2B = 'TRUE';
            $afterpay->CompanyCOCRegistration = $_POST["buckaroo-afterpay-CompanyCOCRegistration"];
            $afterpay->CompanyName = $_POST["buckaroo-afterpay-CompanyName"];
            // $afterpay->CostCentre = $_POST["buckaroo-afterpay-CostCentre"];
            // $afterpay->VatNumber = $_POST["buckaroo-afterpay-VatNumber"];
        }
        $afterpay->BillingBirthDate = date('Y-m-d', strtotime($birthdate));

        $get_billing_address_1 = getWCOrderDetails($order_id, 'billing_address_1');
        $get_billing_address_2 = getWCOrderDetails($order_id, 'billing_address_2');
        $address_components = fn_buckaroo_get_address_components($get_billing_address_1." ".$get_billing_address_2);
        $afterpay->BillingStreet = $address_components['street'];
        $afterpay->BillingHouseNumber = $address_components['house_number'];
        $afterpay->BillingHouseNumberSuffix = $address_components['number_addition'];
        $afterpay->BillingPostalCode = getWCOrderDetails($order_id, 'billing_postcode');
        $afterpay->BillingCity = getWCOrderDetails($order_id, 'billing_city');
        $afterpay->BillingCountry = getWCOrderDetails($order_id, 'billing_country');
        $get_billing_email = getWCOrderDetails($order_id, 'billing_email');
        $afterpay->BillingEmail = ! empty($get_billing_email) ? $get_billing_email : '';
        $afterpay->BillingLanguage = 'nl';
        $get_billing_phone = getWCOrderDetails($order_id, 'billing_phone');
        $number = $this->cleanup_phone($get_billing_phone);
        $afterpay->BillingPhoneNumber = $number['phone'];


        $afterpay->AddressesDiffer = 'FALSE';
        if (isset($_POST["buckaroo-afterpay-shipping-differ"])) {
            // if (!empty($_POST["buckaroo-afterpay-shipping-differ"])) {
            $afterpay->AddressesDiffer = 'TRUE';

            $get_shipping_first_name = getWCOrderDetails($order_id, 'shipping_first_name');
            $afterpay->ShippingInitials = $this->getInitials($get_shipping_first_name);
            $get_shipping_last_name = getWCOrderDetails($order_id, 'shipping_last_name');
            $afterpay->ShippingLastName = $get_shipping_last_name;
            $get_shipping_address_1 = getWCOrderDetails($order_id, 'shipping_address_1');
            $get_shipping_address_2 = getWCOrderDetails($order_id, 'shipping_address_2');
            $address_components = fn_buckaroo_get_address_components($get_shipping_address_1." ".$get_shipping_address_2);
            $afterpay->ShippingStreet = $address_components['street'];
            $afterpay->ShippingHouseNumber = $address_components['house_number'];
            $afterpay->ShippingHouseNumberSuffix = $address_components['number_addition'];

            $afterpay->ShippingPostalCode = getWCOrderDetails($order_id, 'shipping_postcode');
            $afterpay->ShippingCity = getWCOrderDetails($order_id, 'shipping_city');
            $afterpay->ShippingCountryCode = getWCOrderDetails($order_id, 'shipping_country');


            $get_shipping_email = getWCOrderDetails($order_id, 'billing_email');
            $afterpay->ShippingEmail = ! empty($get_shipping_email) ? $get_shipping_email : '';
            $afterpay->ShippingLanguage = 'nl';
            $get_shipping_phone = getWCOrderDetails($order_id, 'billing_phone');
            $number = $this->cleanup_phone($get_shipping_phone);
            $afterpay->ShippingPhoneNumber = $number['phone'];
        }
        if ($this->type == 'afterpayacceptgiro') {
            if (empty($_POST["buckaroo-afterpay-CustomerAccountNumber"])) {
                wc_add_notice(__("IBAN is required", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            $afterpay->CustomerAccountNumber = $_POST["buckaroo-afterpay-CustomerAccountNumber"];
        }

        $afterpay->CustomerIPAddress = getClientIpBuckaroo();
        $afterpay->Accept = 'TRUE';
        $products = array();
        $items = $order->get_items();
        $itemsTotalAmount = 0;

        foreach ($items as $item) {
            $product = new WC_Product($item['product_id']);
            $tax_class = $product->get_attribute("vat_category");
            if (empty($tax_class)) {
                $tax_class = $this->vattype;
                //wc_add_notice( __("Vat category (vat_category) do not exist for product ", 'wc-buckaroo-bpe-gateway').$item['name'], 'error' );
               // return;
            }
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"] = $item['product_id'];
            $tmp["ArticleQuantity"] = 1;
            $tmp["ArticleUnitprice"] = number_format(number_format($item["line_total"]+$item["line_tax"], 4)/$item["qty"], 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"] * $item["qty"];
            $tmp["ArticleVatcategory"] = $tax_class;
            for ($i = 0 ; $item["qty"] > $i ; $i++) {
                $products[] = $tmp;
            }
        }
        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"] = $key;
            $tmp["ArticleQuantity"] = 1;
            $tmp["ArticleUnitprice"] = number_format(($item["line_total"]+$item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $tmp["ArticleVatcategory"] = '4';
            $products[] = $tmp;
        }
        if (! empty($afterpay->ShippingCosts)) {
            $itemsTotalAmount += $afterpay->ShippingCosts;
        }
        for ($i = 0; count($products) > $i; $i++) {
            if ($afterpay->amountDedit != $itemsTotalAmount) {
                if (number_format($afterpay->amountDedit - $itemsTotalAmount, 2) >= 0.01) {
                    $products[$i]['ArticleUnitprice'] += 0.01;
                    $itemsTotalAmount += 0.01;
                } elseif (number_format($itemsTotalAmount - $afterpay->amountDedit, 2) >= 0.01) {
                    $products[$i]['ArticleUnitprice'] -= 0.01;
                    $itemsTotalAmount -= 0.01;
                }
            }
        }
        
        $afterpay->returnUrl = $this->notify_url;

        if ($this->usenotification == 'TRUE') {
            $afterpay->usenotification = 1;
            $customVars['Customergender'] = $_POST['buckaroo-sepadirectdebit-gender'];

            $get_billing_first_name = getWCOrderDetails($order_id, 'billing_first_name');
            $get_billing_last_name = getWCOrderDetails($order_id, 'billing_last_name');
            $get_billing_email = getWCOrderDetails($order_id, 'billing_email');
            $customVars['CustomerFirstName'] = ! empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['CustomerLastName'] = ! empty($get_billing_last_name) ? $get_billing_last_name : '';
            $customVars['Customeremail'] = ! empty($get_billing_email) ? $get_billing_email : '';
            $customVars['Notificationtype'] = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int) $this->invoicedelay . ' day')).' + '. (int)$this->notificationdelay.' day'));
        }

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
     * Payment form on checkout page
     */
    public function payment_fields()
    {
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
    <?php if ($this->b2b == 'enable' && $this->type== 'afterpaydigiaccept') {
            ?>
    <p class="form-row form-row-wide validate-required">
        <?php echo _e('Checkout for company', 'wc-buckaroo-bpe-gateway')?>
        <input id="buckaroo-afterpay-b2b" name="buckaroo-afterpay-b2b" onclick="CheckoutFields(this.checked)"
            type="checkbox" value="ON" />
    </p>

    <script>
        function CheckoutFields(showFiields) {
            if (showFiields) {
                document.getElementById('showB2BBuckaroo').style.display = 'block';
                document.getElementById('buckaroo-afterpay-CompanyName').value = document.getElementById(
                    'billing_company').value;
                document.getElementById('buckaroo-afterpay-birthdate').disabled = true;
                document.getElementById('buckaroo-afterpay-birthdate').value = '';
                document.getElementById('buckaroo-afterpay-birthdate').parentElement.style.display = 'none';
                document.getElementById('buckaroo-afterpay-birthdate').parentElement.classList.remove(
                    'woocommerce-invalid');
                document.getElementById('buckaroo-afterpay-birthdate').parentElement.classList.remove(
                    'validate-required');
                document.getElementById('buckaroo-afterpay-genderm').disabled = true;
                document.getElementById('buckaroo-afterpay-genderf').disabled = true;
                document.getElementById('buckaroo-afterpay-genderm').parentElement.style.display = 'none';
                document.getElementById('buckaroo-afterpay-genderm').parentElement.getElementsByTagName('span').item(0)
                    .style.display = 'none';
            } else {
                document.getElementById('showB2BBuckaroo').style.display = 'none';
                document.getElementById('buckaroo-afterpay-birthdate').disabled = false;
                document.getElementById('buckaroo-afterpay-birthdate').parentElement.style.display = 'block';
                document.getElementById('buckaroo-afterpay-birthdate').parentElement.classList.add('validate-required');
                document.getElementById('buckaroo-afterpay-genderm').disabled = false;
                document.getElementById('buckaroo-afterpay-genderf').disabled = false;
                document.getElementById('buckaroo-afterpay-genderf').parentElement.style.display = 'inline-block';
                document.getElementById('buckaroo-afterpay-genderf').parentElement.getElementsByTagName('span').item(0)
                    .style.display = 'inline-block';
            }
        }
    </script>

    <span id="showB2BBuckaroo" style="display:none">
        <p class="form-row form-row-wide validate-required">
            <?php echo _e('Fill required fields if bill in on the company:', 'wc-buckaroo-bpe-gateway')?>
        </p>
        <p class="form-row form-row-wide validate-required">
            <label for="buckaroo-afterpay-CompanyCOCRegistration"><?php echo _e('COC (KvK) number:', 'wc-buckaroo-bpe-gateway')?><span
                    class="required">*</span></label>
            <input id="buckaroo-afterpay-CompanyCOCRegistration" name="buckaroo-afterpay-CompanyCOCRegistration"
                class="input-text" type="text" maxlength="250" autocomplete="off" value="" />
        </p>
        <p class="form-row form-row-wide validate-required">
            <label for="buckaroo-afterpay-CompanyName"><?php echo _e('Name of the organization:', 'wc-buckaroo-bpe-gateway')?><span
                    class="required">*</span></label>
            <input id="buckaroo-afterpay-CompanyName" name="buckaroo-afterpay-CompanyName" class="input-text"
                type="text" maxlength="250" autocomplete="off" value="" />
        </p>
    </span>
    <?php
        } ?>

    <p class="form-row">
        <label for="buckaroo-afterpay-gender"><?php echo _e('Gender:', 'wc-buckaroo-bpe-gateway')?><span
                class="required">*</span></label>
        <input id="buckaroo-afterpay-genderm" name="buckaroo-afterpay-gender" class="" type="radio" value="1" checked
            style="float:none; display: inline !important;" /> <?php echo _e('Male', 'wc-buckaroo-bpe-gateway')?>
        &nbsp;
        <input id="buckaroo-afterpay-genderf" name="buckaroo-afterpay-gender" class="" type="radio" value="2"
            style="float:none; display: inline !important;" /> <?php echo _e('Female', 'wc-buckaroo-bpe-gateway')?>
    </p>
    <p class="form-row form-row-wide validate-required">
        <label for="buckaroo-afterpay-birthdate"><?php echo _e('Birthdate (format DD-MM-YYYY):', 'wc-buckaroo-bpe-gateway')?><span
                class="required">*</span></label>
        <input id="buckaroo-afterpay-birthdate" name="buckaroo-afterpay-birthdate" class="input-text" type="text"
            maxlength="250" autocomplete="off" value="" placeholder="DD-MM-YYYY" />
    </p>
    <?php if (! empty($post_data["ship_to_different_address"])) {
            ?>
    <input id="buckaroo-afterpay-shipping-differ" name="buckaroo-afterpay-shipping-differ" class="" type="hidden"
        value="1" />
    <?php
        } ?>
    <?php if ($this->type == 'afterpayacceptgiro') {
            ?>
    <p class="form-row form-row-wide validate-required">
        <label for="buckaroo-afterpay-CustomerAccountNumber"><?php echo _e('IBAN:', 'wc-buckaroo-bpe-gateway')?><span
                class="required">*</span></label>
        <input id="buckaroo-afterpay-CustomerAccountNumber" name="buckaroo-afterpay-CustomerAccountNumber"
            class="input-text" type="text" value="" />
    </p>
    <?php
        } ?>

    <p class="form-row form-row-wide validate-required">
        <a href="https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden/" target="_blank"><?php echo _e('Accept licence agreement:', 'wc-buckaroo-bpe-gateway')?></a><span
            class="required">*</span> <input id="buckaroo-afterpay-accept" name="buckaroo-afterpay-accept"
            type="checkbox" value="ON" />
    </p>
    <p class="required" style="float:right;">* Verplicht</p>
</fieldset>
<?php
    }
    
    /**
     * Check response data
     *
     * @access public
     */
    public function response_handler()
    {
        $woocommerce = getWooCommerceObject();
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
        $this->form_fields['service'] = [
            'title' => __('Select afterpay service', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Please select the service', 'wc-buckaroo-bpe-gateway'),
            'options' => ['afterpayacceptgiro'=>'Offer customer to pay afterwards by SEPA Direct Debit.', 'afterpaydigiaccept'=>'Offer customer to pay afterwards by digital invoice.'],
            'default' => 'afterpaydigiaccept'];

        $this->form_fields['enable_bb'] = [
            'title' => __('Enable B2B option for AfterPay', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Enables or disables possibility to pay using company credentials', 'wc-buckaroo-bpe-gateway'),
            'options' => ['enable'=>'Enable', 'disable'=>'Disable'],
            'default' => 'disable'];

        $this->form_fields['vattype'] = [
            'title' => __('Default product Vat type', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Please select default vat type for your products', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                '1'=>'1 = High rate',
                '2'=>'2 = Low rate',
                '3'=>'3 = Zero rate',
                '4'=>'4 = Null rate',
                '5'=>'5 = middle rate'],
            'default' => '1'];

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

        $this->form_fields['afterpaypayauthorize'] = [
                'title' => __('AfterPay Pay or Capture', 'wc-buckaroo-bpe-gateway'),
                'type' => 'select',
                'description' => __('Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway'),
                'options' => ['pay' => 'Pay', 'authorize' => 'Authorize'],
                'default' => 'pay'];
    }
}
