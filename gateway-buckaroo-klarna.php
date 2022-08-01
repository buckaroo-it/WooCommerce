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
        $this->setIcon('24x24/klarna.svg', 'svg/Klarna.svg');
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
        $gender = $this->request($this->getKlarnaSelector() . '-gender');

        if(!in_array($gender, ["1","2"])) {
            wc_add_notice(__("Unknown gender", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if ($this->request('ship_to_different_address') !== null) {
            $countryCode =$this->request('shipping_country') == 'NL' ?$this->request('shipping_country') : '';
            $countryCode =$this->request('billing_country') == 'NL' ?$this->request('billing_country') : $countryCode;
            if (!empty($countryCode)
                && strtolower($this->klarnaPaymentFlowId) !== 'pay') {

                return wc_add_notice(__('Payment method is not supported for country ' . '(' . esc_html($countryCode) . ')', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            if (
                ($this->request('billing_country') == 'NL')
                && strtolower($this->klarnaPaymentFlowId) !== 'pay'
                ) {

                return wc_add_notice(__('Payment method is not supported for country ' . '(' . esc_html($this->request('billing_country')) . ')', 'wc-buckaroo-bpe-gateway'), 'error');
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
        $klarna->setType($this->type);

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

        $order_details = new Buckaroo_Order_Details($order);
        
        $klarna = $this->getBillingInfo($order_details, $klarna);
        $klarna = $this->getShippingInfo($order_details, $klarna);
        $klarna = $this->handleThirdPartyShippings($klarna, $order, $this->country);

        $klarna->CustomerIPAddress = getClientIpBuckaroo();
        $klarna->Accept            = 'TRUE';
        $products = $this->getProductsInfo($order, $klarna->amountDedit, $klarna->ShippingCosts);

        $klarna->returnUrl = $this->notify_url;

        $klarna->setPaymentFlow($this->getKlarnaPaymentFlow());
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
        $method->BillingGender = $this->request($this->getKlarnaSelector() . '-gender') ?? 'Unknown';
        $method->BillingFirstName = $order_details->getBilling('first_name');
        if (empty($method->BillingPhoneNumber)) {
            $method->BillingPhoneNumber = $this->request($this->getKlarnaSelector() . "-phone");
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
        if ($this->request($this->getKlarnaSelector() . "-shipping-differ")) {
            $method->AddressesDiffer = 'TRUE';

            $shippingCompany = $order_details->getShipping('company');
            $method->setShippingCategory($shippingCompany);

            /** @var BuckarooKlarna */
            $method = $this->set_shipping($method, $order_details);
            $method->ShippingFirstName = $order_details->getShipping('first_name');
        }
        return $method;
    }
  
    public function getProductImage($product) {
        $imgTag = $product->get_image();	
        $doc = new DOMDocument();	
        $doc->loadHTML($imgTag);	
        $xpath = new DOMXPath($doc);	
        $imageUrl = $xpath->evaluate("string(//img/@src)");
        
        return $imageUrl;
    }

    public function getProductSpecific($product, $item, $tmp) { 
        //Product
        $data['product_tmp'] = $tmp;
        $data['product_tmp']['ArticleUnitprice'] = number_format(number_format($item['line_total'] + $item['line_tax'], 4) / $item['qty'], 2);
        $data['product_tmp']['ProductUrl'] = get_permalink($item['product_id']);
        $imgUrl = $this->getProductImage($product);
        //Don't send the tag if imgurl not set
        if(!empty($imgUrl)){
            $data['product_tmp']['ImageUrl'] = $imgUrl;
        }
        
        $data['product_itemsTotalAmount'] = number_format($data['product_tmp']['ArticleUnitprice'] * $item['qty'], 2);

        return $data;
    }
}
