<?php


/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Afterpaynew extends WC_Gateway_Buckaroo
{
    public $type;
    public $b2b;
    public $vattype;
    public $sendimageinfo;
    protected $afterpaynewpayauthorize;
    protected $customer_type;

    public const CUSTOMER_TYPE_B2C = 'b2c';
    public const CUSTOMER_TYPE_B2B = 'b2b';
    public const CUSTOMER_TYPE_BOTH = 'both';

    public function __construct()
    {
        $this->id                     = 'buckaroo_afterpaynew';
        $this->title                  = 'Riverty | AfterPay (by Buckaroo)';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo Riverty | AfterPay New';
        $this->set_icon('afterpay.png', 'svg/afterpay.svg');

        parent::__construct();
        $this->add_refund_support();
    }

    /** @inheritDoc */
    public function get_sdk_code(): string
    {
        return 'afterpay';
    }

    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->afterpaynewpayauthorize = $this->get_option('afterpaynewpayauthorize');
        $this->sendimageinfo = $this->get_option('sendimageinfo');
        $this->vattype    = $this->get_option('vattype');
        $this->type       = 'afterpay';
        $this->customer_type = $this->get_option('customer_type', self::CUSTOMER_TYPE_BOTH);
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


    public function process_capture()
    {
        // $order_id = $this->request('order_id');
        
        // if ($order_id === null || !is_numeric($order_id)) {
        //     return $this->create_capture_error(__('A valid order number is required'));
        // }

        // $capture_amount = $this->request('capture_amount');
        // if($capture_amount === null || !is_scalar($capture_amount)) {
        //     return $this->create_capture_error(__('A valid capture amount is required'));
        // }

        // $previous_captures = get_post_meta($order_id, '_wc_order_captures') ? get_post_meta($order_id, '_wc_order_captures') : false;

        // $woocommerce          = getWooCommerceObject();

        // $order = getWCOrder($order_id);
        // /** @var BuckarooAfterPayNew */
        // // $afterpay = $this->createDebitRequest($order);
        // $afterpay->amountDedit            = str_replace(',', '.', $capture_amount);
        // $afterpay->OriginalTransactionKey = $order->get_transaction_id();
        // $afterpay->invoiceId              = (string) getUniqInvoiceId($woocommerce->order ? $woocommerce->order->get_order_number() : $order_id) . (is_array($previous_captures) ? '-' . count($previous_captures) : "");

        // // add items to capture call for afterpay
        // $customVars['payment_issuer'] = get_post_meta($order_id, '_wc_order_payment_issuer', true);

        // $products         = array();
        // $items            = $order->get_items();
        // $itemsTotalAmount = 0;

        // $line_item_qtys         = buckaroo_request_sanitized_json('line_item_qtys');
		// $line_item_totals       = buckaroo_request_sanitized_json('line_item_totals');
		// $line_item_tax_totals   = buckaroo_request_sanitized_json('line_item_tax_totals');

        // foreach ($items as $item) {
        //     if (isset($line_item_qtys[$item->get_id()]) && $line_item_qtys[$item->get_id()] > 0) {
        //         $product = new WC_Product($item['product_id']);

        //         $tax                       = new WC_Tax();
        //         $taxes                     = $tax->get_rates($product->get_tax_class());
        //         $rates                     = array_shift($taxes);
        //         $itemRate                  = number_format(array_shift($rates), 2);
        //         $tmp["ArticleDescription"] = $item['name'];
        //         $tmp["ArticleId"]          = $item['product_id'];
        //         $tmp["ArticleQuantity"]    = $line_item_qtys[$item->get_id()];
        //         $tmp["ArticleUnitprice"]   = (float) number_format(number_format($item["line_total"] + $item["line_tax"], 4, '.', '') / $item["qty"], 2, '.', '');
        //         $itemsTotalAmount += $tmp["ArticleUnitprice"] * $item["qty"];
        //         $tmp["ArticleVatcategory"] = $itemRate;
        //         $products[]                = $tmp;
        //     }
        // }

        // if (!$previous_captures) {
        //     $fees = $order->get_fees();
        //     foreach ($fees as $key => $item) {
        //         $feeTaxRate = $this->getProductTaxRate($item);
        //         $tmp["ArticleDescription"] = $item['name'];
        //         $tmp["ArticleId"] = $key;
        //         $tmp["ArticleQuantity"] = 1;
        //         $tmp["ArticleUnitprice"] = number_format(($item["line_total"] + $item["line_tax"]), 2, '.', '');
        //         $itemsTotalAmount += $tmp["ArticleUnitprice"];
        //         $tmp["ArticleVatcategory"] = $feeTaxRate;
        //         $products[] = $tmp;
        //     }
        // }

        // // Add shippingCosts
        // $shippingInfo = $this->getAfterPayShippingInfo('afterpay', 'capture', $order, $line_item_totals, $line_item_tax_totals);
        // if ($shippingInfo['costs'] > 0) {
        //     $products[] = $shippingInfo['shipping_virtual_product'];
        // }

        // // end add items

        // $response         = $afterpay->Capture($customVars, $products);
        // $process_response = fn_buckaroo_process_capture($response, $order, $this->currency, $products);

        // return $process_response;

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

        $birthdate = $this->parse_date(
            $this->request('buckaroo-afterpaynew-birthdate')
        );

	    if (!($this->validate_date($birthdate, 'd-m-Y') && $this->validate_birthdate($birthdate)) && in_array($country, ['NL', 'BE']) ) {
            wc_add_notice(__("You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if ($this->request("buckaroo-afterpaynew-accept") === null) {
            wc_add_notice(__("Please accept licence agreements", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if (
            self::CUSTOMER_TYPE_B2C !== $this->customer_type &&
            $country === 'NL' &&
            $this->request('billing_company') !== null
        ) {
            if ($this->request("buckaroo-afterpaynew-company-coc-registration") === null) {
                wc_add_notice(__("Company registration number is required", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if ($this->request('buckaroo-afterpaynew-phone') === null && $this->request('billing_phone') === null) {
            wc_add_notice(__("Please enter phone number", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if (
            $this->is_house_number_invalid('billing')
        ) {
            wc_add_notice(__("Invalid billing address, cannot find house number", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if (
            $this->is_house_number_invalid('shipping') &&
            $this->request('ship_to_different_address') == 1
        ) {
            wc_add_notice(__("Invalid shipping address, cannot find house number", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        parent::validate_fields();
    }

    private function is_house_number_invalid($type)
    {
        $components = new Buckaroo_Address_Components(
            $this->request($type.'_address_1') . " " . $this->request($type.'_address_2')
        );

        return empty(trim($components->get_house_number()));
    }
 
    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->add_financial_warning_field();
        $this->form_fields['afterpaynewpayauthorize'] = array(
            'title'       => __('Riverty | AfterPay Pay or Capture', 'wc-buckaroo-bpe-gateway'),
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
        $this->form_fields['customer_type'] = array(
            'title'       => __('Riverty | AfterPay customer type', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('This setting determines whether you accept Riverty | AfterPay payments for B2C, B2B or both customer types. When B2B is selected, this method is only shown when a company name is entered in the checkout process.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array(
                self::CUSTOMER_TYPE_BOTH => __('Both'),
                self::CUSTOMER_TYPE_B2C => __('B2C (Business-to-consumer)'),
                self::CUSTOMER_TYPE_B2B => __('B2B ((Business-to-Business)'),
            ),
            'default'     => self::CUSTOMER_TYPE_BOTH
        );
        $this->form_fields['b2b_min_value'] = array(
            'title'             => __('Min order amount  for B2B', 'wc-buckaroo-bpe-gateway'),
            'type'              => 'number',
            'custom_attributes' => ['step' => '0.01'],
            'description'       => __('The payment method shows only for orders with an order amount greater than the minimum amount.', 'wc-buckaroo-bpe-gateway'),
            'default'           => '0',
        );
        $this->form_fields['b2b_max_value'] = array(
            'title'             => __('Max order amount  for B2B', 'wc-buckaroo-bpe-gateway'),
            'type'              => 'number',
            'custom_attributes' => ['step' => '0.01'],
            'description'       => __('The payment method shows only for orders with an order amount smaller than the maximum amount.', 'wc-buckaroo-bpe-gateway'),
            'default'           => '0',
        );
    }

    /**
     * Show payment if available
     *
     * @param float $cartTotal
     *
     * @return boolean
     */
    public function isAvailable(float $cartTotal)
    {
        if ($this->customer_type !== self::CUSTOMER_TYPE_B2B) {
            return $this->isAvailableB2B($cartTotal);
        }

        return true;
    }
    /**
     * Check if payment is available for b2b
     *
     * @param float $cartTotal
     *
     * @return boolean
     */
    public function isAvailableB2B(float $cartTotal)
    {
        $b2bMin = $this->get_option('b2b_min_value', 0);
        $b2bMax = $this->get_option('b2b_max_value', 0);

        if ($b2bMin == 0 && $b2bMax == 0) {
            return true;
        }
        
        return ($b2bMin > 0 && $cartTotal > $b2bMin) || ($b2bMax > 0 && $cartTotal < $b2bMax);
    }
}
