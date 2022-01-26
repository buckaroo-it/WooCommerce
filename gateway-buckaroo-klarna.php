<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/klarna/klarna.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Klarna extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooKlarna::class;
    protected $type;
    protected $currency;
    protected $klarnaPaymentFlowId = '';

    public function __construct()
    {
        $this->has_fields = true;
        $this->type       = 'klarna';
        $this->setIcon('24x24/klarna.svg', 'new/Klarna.png');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->vattype = $this->get_option('vattype');
    }
    public function getKlarnaSelector()
    {
        return str_replace("_", "-", $this->id);
    }

    public function getKlarnaPaymentFlow()
    {
        return $this->klarnaPaymentFlowId;
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
        return $this->processDefaultRefund($order_id, $amount, $reason);
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
     */
    public function validate_fields()
    {
        if (!empty($_POST['ship_to_different_address'])) {
            $countryCode = $_POST['shipping_country'] == 'NL' ? $_POST['shipping_country'] : '';
            $countryCode = $_POST['billing_country'] == 'NL' ? $_POST['billing_country'] : $countryCode;
            if (!empty($countryCode)
                && strtolower($this->klarnaPaymentFlowId) !== 'pay') {

                return wc_add_notice(__('Payment method is not supported for country ' . '(' . $countryCode . ')', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            if (($_POST['billing_country'] == 'NL')
                && strtolower($this->klarnaPaymentFlowId) !== 'pay') {

                return wc_add_notice(__('Payment method is not supported for country ' . '(' . $_POST['billing_country'] . ')', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $this->setOrderCapture($order_id, 'Klarna');

        $order = getWCOrder($order_id);
        /** @var BuckarooKlarna */
        $klarna = $this->createDebitRequest($order);
        $klarna->invoiceId = (string)getUniqInvoiceId(
            preg_replace('/\./', '-', $order->get_order_number())
        );

      

        $shippingCosts    = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $klarna->ShippingCosts = number_format($shippingCosts, 2) + number_format($shippingCostsTax, 2);
        }
        if (floatval($shippingCostsTax) > 0) {
            $klarna->ShippingCostsTax = number_format(($shippingCostsTax * 100) / $shippingCosts);
        }

        $order_details = new Buckaroo_Order_Details($order_id);
        
        $klarna = $this->getBillingInfo($order_details, $klarna);
        $klarna = $this->getShippingInfo($order_details, $klarna);
        $klarna = $this->handleThirdPartyShippings($klarna, $order, $this->country);

        $klarna->CustomerIPAddress = getClientIpBuckaroo();
        $klarna->Accept            = 'TRUE';
        $products = $this->getProductsInfo($order, $klarna->amountDedit, $klarna->ShippingCosts);

        $klarna->returnUrl = $this->notify_url;

        $klarna->setPaymnetFlow($this->getKlarnaPaymentFlow());
        $response = $klarna->paymentAction($products);
        return fn_buckaroo_process_response($this, $response, $this->mode);
    }
    /**
     * Get billing info for pay request
     *
     * @param Buckaroo_Order_Details $order_details
     * @param BuckarooKlarna $method
     * @param string $birthdate
     *
     * @return BuckarooKlarna  $method
     */
    protected function getBillingInfo($order_details, $method)
    {
        /** @var BuckarooKlarna */
        $method = $this->set_billing($method, $order_details);
        $method->BillingGender = $_POST[$this->getKlarnaSelector() . '-gender'] ?? 'Unknown';
        $method->BillingFirstName = $order_details->getBilling('first_name');
        if (empty($method->BillingPhoneNumber)) {
            $method->BillingPhoneNumber =  $_POST[$this->getKlarnaSelector() . "-phone"];
        }


        $billingCompany =$order_details->getBilling('company');
        $method->setBillingCategory($billingCompany);
        $method->setShippingCategory($billingCompany);

        return $method;
    }
    /**
     * Get shipping info for pay request
     *
     * @param Buckaroo_Order_Details $order_details
     * @param BuckarooKlarna $method
     *
     * @return BuckarooKlarna $method
     */
    protected function getShippingInfo($order_details, $method)
    {
        $method->AddressesDiffer = 'FALSE';
        if (isset($_POST[$this->getKlarnaSelector() . "-shipping-differ"])) {
            $method->AddressesDiffer = 'TRUE';

            $shippingCompany = $order_details->getShipping('company');
            $method->setShippingCategory($shippingCompany);

            /** @var BuckarooKlarna */
            $method = $this->set_shipping($method, $order_details);
            $method->ShippingFirstName = $order_details->getShipping('first_name');
        }
        return $method;
    }
    private function getProductsInfo($order, $amountDedit, $shippingCosts)
    {

        $products                  = array();
        $items                     = $order->get_items();
        $itemsTotalAmount          = 0;

        $feeItemRate = 0;
        foreach ($items as $item) {
            $product = new WC_Product($item['product_id']);
            $imgTag  = $product->get_image();
            $doc = new DOMDocument();
            $doc->loadHTML($imgTag);
            $xpath   = new DOMXPath($doc);
            $src     = $xpath->evaluate("string(//img/@src)");

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
            $tmp["ArticleUnitprice"]   = number_format(number_format($item["line_total"] + $item["line_tax"], 4) / $item["qty"], 2);
            $itemsTotalAmount += number_format($tmp["ArticleUnitprice"] * $item["qty"], 2);

            $tmp["ArticleVatcategory"] = $itemRate;
            $tmp["ProductUrl"]         = get_permalink($item['product_id']);
            $tmp["ImageUrl"]           = $src;
            $products[]                = $tmp;
            $feeItemRate               = $feeItemRate > $itemRate ? $feeItemRate : $itemRate;
        }

        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {
            $feeTaxRate                = $this->getFeeTax($fees[$key]);
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $key;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = number_format(($item["line_total"] + $item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $tmp["ArticleVatcategory"] = $feeTaxRate;
            $products[]                = $tmp;
        }

        if (!empty($shippingCosts)) {
            $itemsTotalAmount += $shippingCosts;
        }

        if ($amountDedit != $itemsTotalAmount) {
            if (number_format($amountDedit - $itemsTotalAmount, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"] = 0;
                $products[]                = $tmp;
                $itemsTotalAmount += 0.01;
            } elseif (number_format($itemsTotalAmount - $amountDedit, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"] = 0;
                $products[]                = $tmp;
                $itemsTotalAmount -= 0.01;
            }
        }

        return $products;

    }
    private function getFeeTax($fee)
    {
        $feeInfo    = WC_Tax::get_rates($fee->get_tax_class());
        $feeInfo    = array_shift($feeInfo);
        $feeTaxRate = $feeInfo['rate'] ?? 0;

        return $feeTaxRate;
    }
}
