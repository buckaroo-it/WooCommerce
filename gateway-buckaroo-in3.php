<?php


require_once dirname(__FILE__) . '/library/api/paymentmethods/in3/in3.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_In3 extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooIn3::class;
    public $type;
    public $vattype;
    public $country;
    public function __construct()
    {
        $this->id                     = 'buckaroo_in3';
        $this->title                  = 'In3';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo In3';
        $this->setIcon('24x24/in3.png', 'new/In3.png');

        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->type       = 'in3';
        $this->vattype    = $this->get_option('vattype');
    }
    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '', $line_item_qtys = null, $line_item_totals = null, $line_item_tax_totals = null, $originalTransactionKey = null)
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
        $country = isset($_POST['billing_country']) ? $_POST['billing_country'] : $this->country;

        if ($country === 'NL') {
            if (strtolower($_POST['buckaroo-in3-orderas']) != 'debtor') {
                if (empty($_POST['buckaroo-in3-coc'])) {
                    wc_add_notice(__("Please enter CoC number", 'wc-buckaroo-bpe-gateway'), 'error');
                }
    
                if (empty($_POST['buckaroo-in3-companyname'])) {
                    wc_add_notice(__("Please enter company name", 'wc-buckaroo-bpe-gateway'), 'error');
                }
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
        $this->setOrderCapture($order_id, 'In3');

        $order = getWCOrder($order_id);
        /** @var BuckarooIn3 */
        $in3 = $this->createDebitRequest($order);
        $in3->CustomerType = $_POST["buckaroo-in3-orderas"];

        if (strtolower($in3->CustomerType) != 'debtor') {
            $in3->cocNumber   = $_POST["buckaroo-in3-coc"];
            $in3->companyName = $_POST["buckaroo-in3-companyname"];
        }
        $order_details = new Buckaroo_Order_Details($order);
        
        $birthdate            = $_POST['buckaroo-in3-birthdate'];
        if ($this->validateDate($birthdate, 'd-m-Y')) {
            $birthdate = date('Y-m-d', strtotime($birthdate));
        } elseif (in_array($order_details->getBilling('country'), ['NL'])) {
            wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }

        $shippingCosts    = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $in3->ShippingCosts = number_format($shippingCosts, 2) + number_format($shippingCostsTax, 2);
        }
        if (floatval($shippingCostsTax) > 0) {
            $in3->ShippingCostsTax = number_format(($shippingCostsTax * 100) / $shippingCosts);
        }
        
        $in3 = $this->getBillingInfo($order_details, $in3, $birthdate);
        
        $in3->InvoiceDate              = date("d-m-Y");
        $in3->CustomerIPAddress = getClientIpBuckaroo();
        $in3->Accept            = 'TRUE';
        $products               = array();
        $items                  = $order->get_items();
        $itemsTotalAmount       = 0;

        foreach ($items as $item) {
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $item['product_id'];
            $tmp["ArticleQuantity"]    = $item["qty"];
            $tmp["ArticleUnitprice"]   = number_format(number_format($item["line_total"] + $item["line_tax"], 4) / $item["qty"], 2);
            $itemsTotalAmount += number_format($tmp["ArticleUnitprice"] * $item["qty"], 2);

            $products['product'][] = $tmp;
        }

        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $key;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = number_format(($item["line_total"] + $item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $products['fee'] = $tmp;
        }
        if (!empty($in3->ShippingCosts)) {
            $itemsTotalAmount += $in3->ShippingCosts;
        }

        if ($in3->amountDedit != $itemsTotalAmount) {
            if (number_format($in3->amountDedit - $itemsTotalAmount, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($in3->amountDedit - $itemsTotalAmount, 2);

                $products['product'][] = $tmp;
                $itemsTotalAmount += 0.01;
            } elseif (number_format($itemsTotalAmount - $in3->amountDedit, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($in3->amountDedit - $itemsTotalAmount, 2);

                $products['product'][] = $tmp;
                $itemsTotalAmount -= 0.01;
            }
        }

        $in3->returnUrl = $this->notify_url;
        $in3->in3Version = $this->settings['in3version'];
        $action          = 'PayInInstallments';

        $response = $in3->PayIn3($products, $action);
        return fn_buckaroo_process_response($this, $response, $this->mode);
    }
    /**
     * Get billing info for pay request
     *
     * @param Buckaroo_Order_Details $order_details
     * @param BuckarooIn3 $method
     * @param string $birthdate
     *
     * @return BuckarooIn3  $method
     */
    protected function getBillingInfo($order_details, $method, $birthdate)
    {
        /** @var BuckarooIn3 */
        $method = $this->set_billing($method, $order_details);
        $method->BillingGender    = $_POST['buckaroo-in3-gender'];
        $method->BillingInitials  = $order_details->getInitials(
            $order_details->getBilling('first_name')
        );
        $method->BillingBirthDate = date('Y-m-d', strtotime($birthdate));

        return $method;
    }
    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        
        $this->form_fields['in3version'] = array(
            'title'       => __('In3 version', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Choose In3 version', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('false' => 'In3 Flexible', 'true' => 'In3 Garant'),
            'default'     => 'pay');
    }
}
