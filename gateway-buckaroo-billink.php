<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/billink/billink.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Billink extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooBillink::class;
    public $type;
    public $b2b;
    public $vattype;
    public $country;

    public function __construct()
    {

        $this->id                     = 'buckaroo_billink';
        $this->title                  = 'Billink - postpay';
        $this->has_fields             = true;
        $this->method_title           = 'Buckaroo Billink';
        $this->setIcon('24x24/billink.png', 'new/Billink.png');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->type = 'billink';
        $this->vattype    = $this->get_option('vattype');
    }
    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $this->setOrderCapture($order_id, 'Billink');

        $order = getWCOrder($order_id);
        /** @var BuckarooBillink */
        $billink = $this->createDebitRequest($order);
        $billink->invoiceId = (string)getUniqInvoiceId(
            preg_replace('/\./', '-', $order->get_order_number())
        );

        $billink->B2B         = getWCOrderDetails($order_id, "billing_company");
        $billink->BillingGender = $_POST['buckaroo-billink-gender'];

        $get_billing_first_name = getWCOrderDetails($order_id, "billing_first_name");
        $get_billing_last_name  = getWCOrderDetails($order_id, "billing_last_name");

        $billink->setCategory($billink->B2B ? 'B2B': 'B2C');
        $billink->setCompany($billink->B2B ? getWCOrderDetails($order_id, "billing_company"): '');

        $billink->BillingInitials = strtoupper(substr($get_billing_first_name, 0, 1));
        $billink->setBillingFirstName($get_billing_first_name);
        $billink->BillingLastName = $get_billing_last_name;

        if ($billink->B2B) {
            if (!empty($_POST['buckaroo-billink-CompanyCOCRegistration'])) {
                $billink->CompanyCOCRegistration = $_POST['buckaroo-billink-CompanyCOCRegistration'];
            } else {
                wc_add_notice(__("Please enter correct COC (KvK) number", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }

            if (!empty($_POST['buckaroo-billink-VatNumber'])) {
                $billink->VatNumber = $_POST['buckaroo-billink-VatNumber'];
            }
        } else {
            if (!empty($_POST['buckaroo-billink-birthdate']) && $this->validateDate($_POST['buckaroo-billink-birthdate'], 'd-m-Y')) {
                $billink->BillingBirthDate = $_POST['buckaroo-billink-birthdate'];
            } else {
                wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
        }
        if (empty($_POST["buckaroo-billink-accept"])) {
            wc_add_notice(__("Please accept licence agreements", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }
        $shippingCosts    = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $billink->ShippingCosts = number_format($shippingCosts, 2) + number_format($shippingCostsTax, 2);
        }
        if (floatval($shippingCostsTax) > 0) {
            $billink->ShippingCostsTax = number_format(($shippingCostsTax * 100) / $shippingCosts);
        }

        $get_billing_address_1             = getWCOrderDetails($order_id, 'billing_address_1');
        $get_billing_address_2             = getWCOrderDetails($order_id, 'billing_address_2');
        $address_components                = fn_buckaroo_get_address_components($get_billing_address_1 . " " . $get_billing_address_2);
        $billink->BillingStreet            = $address_components['street'];
        $billink->BillingHouseNumber       = $address_components['house_number'];
        $billink->BillingHouseNumberSuffix = $address_components['number_addition'];
        $billink->BillingPostalCode        = getWCOrderDetails($order_id, 'billing_postcode');
        $billink->BillingCity              = getWCOrderDetails($order_id, 'billing_city');
        $billink->BillingCountry           = getWCOrderDetails($order_id, 'billing_country');
        $get_billing_email                 = getWCOrderDetails($order_id, 'billing_email');
        $billink->BillingEmail             = !empty($get_billing_email) ? $get_billing_email : '';
        $billink->BillingLanguage          = 'nl';
        $get_billing_phone                 = getWCOrderDetails($order_id, 'billing_phone');
        $number                            = $this->cleanup_phone($get_billing_phone);
        $billink->BillingPhoneNumber       = $number['phone'];

        $billink->AddressesDiffer = 'FALSE';
        if (isset($_POST["buckaroo-billink-shipping-differ"])) {
            $billink->AddressesDiffer = 'TRUE';

            $get_shipping_first_name            = getWCOrderDetails($order_id, 'shipping_first_name');
            $billink->ShippingInitials          = strtoupper(substr($get_shipping_first_name, 0, 1));
            $billink->ShippingFirstName         = $get_shipping_first_name;
            $get_shipping_last_name             = getWCOrderDetails($order_id, 'shipping_last_name');
            $billink->ShippingLastName          = $get_shipping_last_name;
            $get_shipping_address_1             = getWCOrderDetails($order_id, 'shipping_address_1');
            $get_shipping_address_2             = getWCOrderDetails($order_id, 'shipping_address_2');
            $address_components                 = fn_buckaroo_get_address_components($get_shipping_address_1 . " " . $get_shipping_address_2);
            $billink->ShippingStreet            = $address_components['street'];
            $billink->ShippingHouseNumber       = $address_components['house_number'];
            $billink->ShippingHouseNumberSuffix = $address_components['number_addition'];

            $billink->ShippingPostalCode  = getWCOrderDetails($order_id, 'shipping_postcode');
            $billink->ShippingCity        = getWCOrderDetails($order_id, 'shipping_city');
            $billink->ShippingCountryCode = getWCOrderDetails($order_id, 'shipping_country');
            $billink->ShippingGender      = 'Male';

            $get_shipping_email           = getWCOrderDetails($order_id, 'billing_email');
            $billink->ShippingEmail       = !empty($get_shipping_email) ? $get_shipping_email : '';
            $get_shipping_phone           = getWCOrderDetails($order_id, 'billing_phone');
            $number                       = $this->cleanup_phone($get_shipping_phone);
            $billink->ShippingPhoneNumber = $number['phone'];
        }

        $billink->CustomerIPAddress = getClientIpBuckaroo();
        $billink->Accept            = 'TRUE';
        $products                   = array();
        $items                      = $order->get_items();
        $itemsTotalAmount           = 0;

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

            $tmp["ArticleDescription"]   = $item['name'];
            $tmp["ArticleId"]            = $item['product_id'];
            $tmp["ArticleQuantity"]      = $item["qty"];
            $tmp["ArticleUnitpriceExcl"] = number_format($item["line_total"] / $item["qty"], 2);
            $tmp["ArticleUnitpriceIncl"] = number_format(number_format($item["line_total"] + $item["line_tax"], 4) / $item["qty"], 2);
            $itemsTotalAmount += number_format($tmp["ArticleUnitpriceIncl"] * $item["qty"], 2);

            $tmp["ArticleVatcategory"] = $itemRate;
            $products[]                = $tmp;
            $feeItemRate               = $feeItemRate > $itemRate ? $feeItemRate : $itemRate;
        }

        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {

            $feeTaxRate = $this->getFeeTax($fees[$key]);

            $tmp["ArticleDescription"]   = $item['name'];
            $tmp["ArticleId"]            = $key;
            $tmp["ArticleQuantity"]      = 1;
            $tmp["ArticleUnitpriceExcl"] = number_format($item["line_total"], 2);
            $tmp["ArticleUnitpriceIncl"] = number_format(($item["line_total"] + $item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitpriceIncl"];
            $tmp["ArticleVatcategory"] = $feeTaxRate;
            $products[]                = $tmp;
        }
        if (!empty($billink->ShippingCosts)) {
            $itemsTotalAmount += $billink->ShippingCosts;
        }

        if ($billink->amountDedit != $itemsTotalAmount) {
            if (number_format($billink->amountDedit - $itemsTotalAmount, 2) >= 0.01) {
                $tmp["ArticleDescription"]   = 'Remaining Price';
                $tmp["ArticleId"]            = 'remaining_price';
                $tmp["ArticleQuantity"]      = 1;
                $tmp["ArticleUnitpriceExcl"] = number_format($billink->amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleUnitpriceIncl"] = number_format($billink->amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"]   = 0;
                $products[]                  = $tmp;
                $itemsTotalAmount += 0.01;
            } elseif (number_format($itemsTotalAmount - $billink->amountDedit, 2) >= 0.01) {
                $tmp["ArticleDescription"]   = 'Remaining Price';
                $tmp["ArticleId"]            = 'remaining_price';
                $tmp["ArticleQuantity"]      = 1;
                $tmp["ArticleUnitpriceExcl"] = number_format($billink->amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleUnitpriceIncl"] = number_format($billink->amountDedit - $itemsTotalAmount, 2);
                $tmp["ArticleVatcategory"]   = 0;
                $products[]                  = $tmp;
                $itemsTotalAmount -= 0.01;
            }
        }

        $billink->returnUrl = $this->notify_url;



        $response = $billink->PayOrAuthorizeBillink($products, 'Pay');
        return fn_buckaroo_process_response($this, $response, $this->mode);
    }

    private function getFeeTax($fee)
    {
        $feeInfo    = WC_Tax::get_rates($fee->get_tax_class());
        $feeInfo    = array_shift($feeInfo);
        $feeTaxRate = $feeInfo['rate'] ?? 0;

        return $feeTaxRate;
    }

    /**
     * Check that a date is valid.
     *
     * @param String $date A date expressed as a string
     * @param String $format The format of the date
     * @return Object Datetime
     * @return Boolean Format correct returns True, else returns false
     */
    public function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && ($d->format($format) == $date);
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
}
