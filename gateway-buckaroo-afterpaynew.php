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
        $action = ucfirst(isset($this->afterpaynewpayauthorize) ? $this->afterpaynewpayauthorize : 'pay');

        if ($action == 'Authorize') {
            // check if order is captured

            $captures         = get_post_meta($order_id, 'buckaroo_capture', false);
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

            $line_item_qtys       = json_decode(stripslashes($_POST['line_item_qtys']), true);
            $line_item_totals     = json_decode(stripslashes($_POST['line_item_totals']), true);
            $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);

            $line_item_qtys_new                 = array();
            $line_item_totals_new               = array();
            $line_item_tax_totals_new           = array();

            $order = wc_get_order($order_id);
            $items = $order->get_items();

            // Items to products
            $item_ids = array();

            foreach ($items as $item) {
                $item_ids[$item->get_id()] = $item->get_product_id();
            }

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
                                        $line_item_qtys_new[$id_to_refund] = $qty_to_refund;
                                        $qty_to_refund                               = 0;
                                    } else {
                                        $line_item_qtys_new[$id_to_refund] = $product['ArticleQuantity'];
                                        $qty_to_refund -= $product['ArticleQuantity'];
                                    }
                                }

                            }
                        }
                    }
                    $totalQtyToRefund += $qty_to_refund;
                }
            }

            // loop for fees
            $fee_items = $order->get_items('fee');

            $feeCostsToRefund = 0;
            foreach ($fee_items as $fee_item) {
                if (isset($line_item_totals[$fee_item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                    $feeCostsToRefund = $line_item_totals[$fee_item->get_id()];
                    $feeIdToRefund    = $fee_item->get_id();
                }
            }

            // loop for shipping costs
            $shipping_item = $order->get_items('shipping');

            $shippingCostsToRefund = 0;
            foreach ($shipping_item as $item) {
                if (isset($line_item_totals[$item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                    $shippingCostsToRefund = $line_item_totals[$item->get_id()];
                    $shippingIdToRefund    = $item->get_id();
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
                                $line_item_totals_new[$shippingIdToRefund]     = $shippingCostsToRefund;
                                $line_item_tax_totals_new[$shippingIdToRefund] = array(1 => 0);
                                $shippingCostsToRefund                                   = 0;
                            } else {
                                $line_item_totals_new[$shippingIdToRefund]     = $product['ArticleUnitprice'];
                                $line_item_tax_totals_new[$shippingIdToRefund] = array(1 => 0);
                                $shippingCostsToRefund -= $product['ArticleUnitprice'];
                            }
                        }
                    } elseif ($product['ArticleId'] == $feeIdToRefund) {
                        // Found the payment fee in the capture.
                        // See if amount is sufficent.
                        if ($feeCostsToRefund > 0) {
                            if ($feeCostsToRefund <= $product['ArticleUnitprice']) {
                                $line_item_totals_new[$feeIdToRefund]     = $feeCostsToRefund;
                                $line_item_tax_totals_new[$feeIdToRefund] = array(1 => 0);
                                $feeCostsToRefund                         = 0;
                            } else {
                                $line_item_totals_new[$feeIdToRefund]     = $product['ArticleUnitprice'];
                                $line_item_tax_totals_new[$feeIdToRefund] = array(1 => 0);
                                $feeCostsToRefund -= $product['ArticleUnitprice'];
                            }
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

            if ($amount > 0) {
                $refund_result = $this->process_partial_refunds(
                    $order_id,
                    $amount,
                    $reason,
                    $line_item_qtys_new,
                    $line_item_totals_new,
                    $line_item_tax_totals_new,
                    $capture['OriginalTransactionKey']
                );
            }

            if ($refund_result !== true) {
                if (isset($refund_result->errors['error_refund'][0])) {
                    return new WP_Error('error_refund_trid', __($result->errors['error_refund'][0]));
                } else {
                    return new WP_Error('error_refund_trid', __("Unexpected error occured while processing refund, please check your transactions in the Buckaroo plaza."));
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
        $shipping_item       = $order->get_items('shipping');
        $shippingTaxClassKey = 0;
        $shippingCosts       = 0;
        foreach ($shipping_item as $item) {
            if (isset($line_item_totals[$item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                $shippingCosts   = $line_item_totals[$item->get_id()];
                $shippingTaxInfo = $item->get_taxes();
                if (isset($line_item_tax_totals[$item->get_id()])) {
                    foreach ($shippingTaxInfo['total'] as $shippingTaxClass => $shippingTaxClassValue) {
                        $shippingTaxClassKey = $shippingTaxClass;
                        $shippingCosts += $shippingTaxClassValue;
                    }
                }
            }
        }
        if ($shippingCosts > 0) {
            // Add virtual shipping cost product
            $tmp["ArticleDescription"] = "Shipping";
            $tmp["ArticleId"]          = BuckarooConfig::SHIPPING_SKU;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = $shippingCosts;
            $tmp["ArticleVatcategory"] = WC_Tax::_get_tax_rate($shippingTaxClassKey)['tax_rate'] ?? 0;
            $products[]                = $tmp;
            $itemsTotalAmount += $shippingCosts;
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
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $afterpay             = new BuckarooAfterPayNew();

        $order = getWCOrder($order_id);

        $afterpay->amountDedit            = str_replace(',', '.', $_POST['capture_amount']);
        $payment_type                     = str_replace('buckaroo_', '', strtolower($this->id));
        $afterpay->OriginalTransactionKey = $order->get_transaction_id();
        $afterpay->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $afterpay->currency               = $this->currency;
        $afterpay->description            = $this->transactiondescription;
        $afterpay->invoiceId              = (string) getUniqInvoiceId($woocommerce->order ? $woocommerce->order->get_order_number() : $order_id) . (is_array($previous_captures) ? '-' . count($previous_captures) : "");
        $afterpay->orderId                = (string) $order_id;
        $afterpay->returnUrl              = $this->notify_url;

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
        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {
            $feeTaxRate                = $this->getFeeTax($fees[$key]);
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $key;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = number_format(($item["line_total"] + $item["line_tax"]), 2, '.', '');
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $tmp["ArticleVatcategory"] = $feeTaxRate;
            $products[]                = $tmp;
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
            $products[]                = $tmp;
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

        if (!empty($_POST['shipping_method'][0]) && ($_POST['shipping_method'][0] == 'dhlpwc-parcelshop')) {
            $dhlConnectorData                    = $order->get_meta('_dhlpwc_order_connectors_data');
            $dhlCountry                          = !empty($this->country) ? $this->country : $_POST['billing_country'];
            $requestPart                         = $dhlCountry . '/' . $dhlConnectorData['id'];
            $dhlParcelShopAddressData            = $this->getDHLParcelShopLocation($requestPart);
            $afterpay->AddressesDiffer           = 'TRUE';
            $afterpay->ShippingStreet            = $dhlParcelShopAddressData->street;
            $afterpay->ShippingHouseNumber       = $dhlParcelShopAddressData->number;
            $afterpay->ShippingPostalCode        = $dhlParcelShopAddressData->postalCode;
            $afterpay->ShippingHouseNumberSuffix = '';
            $afterpay->ShippingCity              = $dhlParcelShopAddressData->city;
            $afterpay->ShippingCountryCode       = $dhlParcelShopAddressData->countryCode;
        }

        if (!empty($_POST['post-deliver-or-pickup']) && $_POST['post-deliver-or-pickup'] == 'post-pickup') {
            $postNL                              = $order->get_meta('_postnl_delivery_options');
            $afterpay->AddressesDiffer           = 'TRUE';
            $afterpay->ShippingStreet            = $postNL['street'];
            $afterpay->ShippingHouseNumber       = $postNL['number'];
            $afterpay->ShippingPostalCode        = $postNL['postal_code'];
            $afterpay->ShippingHouseNumberSuffix = trim(str_replace('-', ' ', $postNL['number_suffix']));
            $afterpay->ShippingCity              = $postNL['city'];
            $afterpay->ShippingCountryCode       = $postNL['cc'];
        }

        if (!empty($_POST['sendcloudshipping_service_point_selected'])) {
            $afterpay->AddressesDiffer = 'TRUE';
            $sendcloudPointAddress     = $order->get_meta('sendcloudshipping_service_point_meta');
            $addressData               = $this->parseSendCloudPointAddress($sendcloudPointAddress['extra']);

            $afterpay->ShippingStreet            = $addressData['street']['name'];
            $afterpay->ShippingHouseNumber       = $addressData['street']['house_number'];
            $afterpay->ShippingPostalCode        = $addressData['postal_code'];
            $afterpay->ShippingHouseNumberSuffix = $addressData['street']['number_addition'];
            $afterpay->ShippingCity              = $addressData['city'];
            $afterpay->ShippingCountryCode       = $afterpay->BillingCountry;
        }

        if (isset($_POST['_myparcel_delivery_options'])) {
            $myparselDeliveryOptions = $order->get_meta('_myparcel_delivery_options');
            if (!empty($myparselDeliveryOptions)) {
                if ($myparselDeliveryOptions = unserialize($myparselDeliveryOptions)) {
                    if ($myparselDeliveryOptions->isPickup()) {
                        $afterpay->AddressesDiffer = 'TRUE';
                        $pickupOptions = $myparselDeliveryOptions->getPickupLocation();
                        $afterpay->ShippingStreet = $pickupOptions->getStreet();
                        $afterpay->ShippingHouseNumber = $pickupOptions->getNumber();
                        $afterpay->ShippingPostalCode = $pickupOptions->getPostalCode();
                        $afterpay->ShippingCity = $pickupOptions->getCity();
                        $afterpay->ShippingCountryCode = $pickupOptions->getCountry();
                    }
                }
            }
        }

        $afterpay->CustomerIPAddress = getClientIpBuckaroo();
        $afterpay->Accept            = 'TRUE';
        $products                    = array();
        $items                       = $order->get_items();
        $itemsTotalAmount            = 0;

        $articlesLooped = [];

        $feeItemRate = 0;
        foreach ($items as $item) {

            $product = new WC_Product($item['product_id']);

            $tax      = new WC_Tax();
            $taxes    = $tax->get_rates($product->get_tax_class());
            $rates    = array_shift($taxes);
            $itemRate = number_format(array_shift($rates), 2);

            if ($product->get_tax_status() != 'taxable') {
                $itemRate = 0;
            }

            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $item['product_id'];
            $tmp["ArticleQuantity"]    = $item["qty"];
            $tmp["ArticleUnitprice"]   = number_format(number_format($item["line_total"] + $item["line_tax"], 4, '.', '') / $item["qty"], 2, '.', '');
            $itemsTotalAmount += number_format($tmp["ArticleUnitprice"] * $item["qty"], 2, '.', '');

            $tmp["ArticleVatcategory"] = $itemRate;
            $tmp["ProductUrl"]         = get_permalink($item['product_id']);
            if ($this->sendimageinfo) {
                $src = get_the_post_thumbnail_url($item['product_id']);
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
                            $tmp["ImageUrl"] = $src;
                        }
                    }
                }
            }
            $products[]                = $tmp;
            $feeItemRate               = $feeItemRate > $itemRate ? $feeItemRate : $itemRate;
        }

        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {

            $feeTaxRate = $this->getFeeTax($fees[$key]);

            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $key;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = number_format(($item["line_total"] + $item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $tmp["ArticleVatcategory"] = $feeTaxRate;
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
                $tmp["ArticleVatcategory"] = 0;
                $products[]                = $tmp;
                $itemsTotalAmount += 0.01;
            } elseif (number_format($itemsTotalAmount - $afterpay->amountDedit, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($afterpay->amountDedit - $itemsTotalAmount, 2, '.', '');
                $tmp["ArticleVatcategory"] = 0;
                $products[]                = $tmp;
                $itemsTotalAmount -= 0.01;
            }
        }

        $afterpay->returnUrl = $this->notify_url;


        $action = ucfirst(isset($this->afterpaynewpayauthorize) ? $this->afterpaynewpayauthorize : 'pay');

        if ($action == 'Authorize') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
        }

        $response = $afterpay->PayOrAuthorizeAfterpay($products, $action);
        return fn_buckaroo_process_response($this, $response, $this->mode);
    }

    private function getFeeTax($fee)
    {
        $feeInfo    = WC_Tax::get_rates($fee->get_tax_class());
        $feeInfo    = array_shift($feeInfo);
        $feeTaxRate = $feeInfo['rate'] ?? 0;

        return $feeTaxRate;
    }

    private function getDHLParcelShopLocation($parcelShopUrl)
    {
        $url  = "https://api-gw.dhlparcel.nl/parcel-shop-locations/" . $parcelShopUrl;
        $data = wp_remote_request($url);

        if ($data['response']['code'] !== 200) {
            throw new Exception(__('Parcel Shop not found'));
        }

        $data = json_decode($data['body']);

        if (empty($data->address)) {
            throw new Exception(__('Parcel Shop address is incorrect'));
        }

        return $data->address;
    }

    private function parseSendCloudPointAddress($addressData)
    {
        $formattedAddress = [];
        $addressData      = explode('|', $addressData);

        $streetData = $addressData[1];
        $cityData   = $addressData[2];

        $formattedCityData = $this->parseSendcloudCityData($cityData);
        $formattedStreet   = $this->formatStreet($streetData);

        $formattedAddress['street']      = $formattedStreet;
        $formattedAddress['postal_code'] = $formattedCityData[0];
        $formattedAddress['city']        = $formattedCityData[1];

        return $formattedAddress;
    }

    private function parseSendcloudCityData($cityData)
    {
        $cityData = preg_split('/\s/', $cityData, 2);

        return $cityData;
    }

    public function formatStreet($street)
    {
        $format = [
            'house_number'    => '',
            'number_addition' => '',
            'name'            => $street,
        ];

        if (preg_match('#^(.*?)([0-9\-]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['house_number'] = trim($matches[2]);
                $format['name']         = trim($matches[3]);
            } else {
                if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
                    $format['name']            = trim($matches[1]);
                    $format['house_number']    = trim($matches[2]);
                    $format['number_addition'] = trim($matches[3]);
                }
            }
        }

        return $format;
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

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_certificate'));

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_hide_local'));

        //Start Dynamic Rendering of Hidden Fields
        $options      = get_option("woocommerce_" . $this->id . "_settings", null);
        $ccontent_arr = array();
        $keybase      = 'certificatecontents';
        $keycount     = 1;
        if (!empty($options["$keybase$keycount"])) {
            while (!empty($options["$keybase$keycount"])) {
                $ccontent_arr[] = "$keybase$keycount";
                $keycount++;
            }
        }
        $while_key                 = 1;
        $selectcertificate_options = array('none' => 'None selected');
        while ($while_key != $keycount) {
            $this->form_fields["certificatecontents$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '',
            );
            $this->form_fields["certificateuploadtime$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $this->form_fields["certificatename$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $selectcertificate_options["$while_key"] = $options["certificatename$while_key"];

            $while_key++;
        }
        $final_ccontent                                          = $keycount;
        $this->form_fields["certificatecontents$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificateuploadtime$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificatename$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');

        $this->form_fields['selectcertificate'] = array(
            'title'       => __('Select Certificate', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Select your certificate by name.', 'wc-buckaroo-bpe-gateway'),
            'options'     => $selectcertificate_options,
            'default'     => 'none',
        );
        $this->form_fields['choosecertificate'] = array(
            'title'       => __('', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'file',
            'description' => __(''),
            'default'     => '');

        
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
}
