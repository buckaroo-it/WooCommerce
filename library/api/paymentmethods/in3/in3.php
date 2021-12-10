<?php
require_once dirname(__FILE__) . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooIn3 extends BuckarooPaymentMethod
{
    public $BillingGender;
    public $BillingInitials;
    public $BillingLastName;
    public $BillingBirthDate;
    public $BillingStreet;
    public $BillingHouseNumber;
    public $BillingHouseNumberSuffix;
    public $BillingPostalCode;
    public $BillingCity;
    public $BillingCountry;
    public $BillingEmail;
    public $BillingPhoneNumber;
    public $BillingLanguage;
    public $IdentificationNumber;
    public $ShippingCosts;
    public $ShippingCostsTax;
    public $CustomerIPAddress;
    public $Accept;
    public $InvoiceDate;
    public $CustomerType;
    public $cocNumber;
    public $companyName;
    public $in3Version;
    public $orderId;

    /**
     * @access public
     * @param string $type
     */
    public function __construct()
    {
        $this->type    = 'Capayable';
        $this->version = '1';
    }

    /**
     * @access public
     * @param array $customVars
     * @return void
     */
    public function Pay($customVars = array())
    {
        return null;
    }

    /**
     * @access public
     * @param array $products
     * @return callable parent::Pay();
     */
    public function PayIn3($products, $action)
    {
        $this->data['customParameters']["order_id"] = $this->orderId;

        $this->data['customVars'][$this->type]["CustomerType"]["value"] = $this->CustomerType;

        $this->data['customVars'][$this->type]["InvoiceDate"]["value"] = $this->InvoiceDate;

        $this->data['customVars'][$this->type]["LastName"]["value"] = $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"]["group"] = 'Person';

        $this->data['customVars'][$this->type]["Culture"]["value"] = 'nl-NL';
        $this->data['customVars'][$this->type]["Culture"]["group"] = 'Person';

        $this->data['customVars'][$this->type]["Initials"]["value"] = $this->BillingInitials;
        $this->data['customVars'][$this->type]["Initials"]["group"] = 'Person';

        $this->data['customVars'][$this->type]["Gender"]["value"] = $this->BillingGender;
        $this->data['customVars'][$this->type]["Gender"]["group"] = 'Person';

        $this->data['customVars'][$this->type]["BirthDate"]["value"] = $this->BillingBirthDate;
        $this->data['customVars'][$this->type]["BirthDate"]["group"] = 'Person';

        $this->data['customVars'][$this->type]["Street"]["value"] = $this->BillingStreet;
        $this->data['customVars'][$this->type]["Street"]["group"] = 'Address';

        $this->data['customVars'][$this->type]["HouseNumber"]["value"] = isset($this->BillingHouseNumber) ? $this->BillingHouseNumber . ' ' : $this->BillingHouseNumber;
        $this->data['customVars'][$this->type]["HouseNumber"]["group"] = 'Address';

        $this->data['customVars'][$this->type]["HouseNumberSuffix"]["value"] = $this->BillingHouseNumberSuffix;
        $this->data['customVars'][$this->type]["HouseNumberSuffix"]["group"] = 'Address';

        $this->data['customVars'][$this->type]["ZipCode"]["value"] = $this->BillingPostalCode;
        $this->data['customVars'][$this->type]["ZipCode"]["group"] = 'Address';

        $this->data['customVars'][$this->type]["City"]["value"] = $this->BillingCity;
        $this->data['customVars'][$this->type]["City"]["group"] = 'Address';

        $this->data['customVars'][$this->type]["Country"]["value"] = $this->BillingCountry;
        $this->data['customVars'][$this->type]["Country"]["group"] = 'Address';

        $this->data['customVars'][$this->type]["Phone"]["value"] = $this->BillingPhoneNumber;
        $this->data['customVars'][$this->type]["Phone"]["group"] = 'Phone';

        $this->data['customVars'][$this->type]["Email"]["value"] = $this->BillingEmail;
        $this->data['customVars'][$this->type]["Email"]["group"] = 'Email';

        // Merge products with same SKU
        $mergedProducts = array();
        foreach ($products['product'] as $product) {
            if (!isset($mergedProducts[$product['ArticleId']])) {
                $mergedProducts[$product['ArticleId']] = $product;
            } else {
                $mergedProducts[$product['ArticleId']]["ArticleQuantity"] += 1;
            }
        }

        $products['product'] = $mergedProducts;

        $i = 1;
        foreach ($products['product'] as $p) {
            $this->data['customVars'][$this->type]["Name"][$i - 1]["value"]     = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["Name"][$i - 1]["group"]     = 'ProductLine';
            $this->data['customVars'][$this->type]["Code"][$i - 1]["value"]     = $p["ArticleId"];
            $this->data['customVars'][$this->type]["Code"][$i - 1]["group"]     = 'ProductLine';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"] = 'ProductLine';
            $this->data['customVars'][$this->type]["Price"][$i - 1]["value"]    = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["Price"][$i - 1]["group"]    = 'ProductLine';
            $i++;
        }

        if (!empty($products['fee'])) {
            $this->data['customVars'][$this->type]["Name"][$i]["value"]     = __('Payment fee', 'wc-buckaroo-bpe-gateway');
            $this->data['customVars'][$this->type]["Name"][$i]["group"]     = 'SubtotalLine';
            $this->data['customVars'][$this->type]["Code"][$i]["value"]     = $products['fee']["ArticleId"];
            $this->data['customVars'][$this->type]["Code"][$i]["group"]     = 'SubtotalLine';
            $this->data['customVars'][$this->type]["Quantity"][$i]["value"] = $products['fee']["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i]["group"] = 'SubtotalLine';
            $this->data['customVars'][$this->type]["Price"][$i]["value"]    = $products['fee']["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["Price"][$i]["group"]    = 'SubtotalLine';
            $i++;
        }

        if (!empty($this->cocNumber)) {
            $this->data['customVars'][$this->type]["ChamberOfCommerce"]["value"] = $this->cocNumber;
            $this->data['customVars'][$this->type]["ChamberOfCommerce"]["group"] = 'Company';
        }

        if (!empty($this->companyName)) {
            $this->data['customVars'][$this->type]["Name"][$i]["value"] = $this->companyName;
            $this->data['customVars'][$this->type]["Name"][$i]["group"] = 'Company';
            $i++;
        }

        $this->data['customVars'][$this->type]["Name"][$i]["value"]     = __('Shipping cost', 'wc-buckaroo-bpe-gateway');
        $this->data['customVars'][$this->type]["Name"][$i]["group"]     = 'SubtotalLine';
        $this->data['customVars'][$this->type]["Code"][$i]["value"]     = __('Shipping', 'wc-buckaroo-bpe-gateway');
        $this->data['customVars'][$this->type]["Code"][$i]["group"]     = 'SubtotalLine';
        $this->data['customVars'][$this->type]["Quantity"][$i]["value"] = '1';
        $this->data['customVars'][$this->type]["Quantity"][$i]["group"] = 'SubtotalLine';
        $this->data['customVars'][$this->type]["Price"][$i]["value"]    = (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0');
        $this->data['customVars'][$this->type]["Price"][$i]["group"]    = 'SubtotalLine';

        $this->data['customVars'][$this->type]["IsInThreeGuarantee"]["value"] = $this->in3Version;
        $this->data['customVars'][$this->type]["IsInThreeGuarantee"]["group"] = '';

        return parent::$action();
    }

    /**
     * Populate generic fields for a refund
     *
     * @access public
     * * @param array $products
     * @throws Exception
     * @return callable $this->RefundGlobal()
     */
    public function In3Refund()
    {
        $this->setServiceTypeActionAndVersion(
            'Capayable',
            'Refund',
            BuckarooPaymentMethod::VERSION_ONE
        );

        return $this->RefundGlobal();
    }

    /**
     * @access public
     * @return callable parent::checkRefundData($data);
     * @param $data array
     * @throws Exception
     */
    public function checkRefundData($data)
    {
        return parent::checkRefundData($data);
    }
}
