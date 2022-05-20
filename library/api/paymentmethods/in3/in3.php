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
        $this->setParameter("customParameters", ["order_id" => $this->getRealOrderId()]);
        $this->setCustomVar("CustomerType", ["value" => $this->CustomerType]);
        $this->setCustomVar("InvoiceDate", ["value" => $this->InvoiceDate]);

        $this->setCustomVar(
            [
                "LastName" => $this->BillingLastName,
                "Culture" =>  'nl-NL',
                "Initials" => $this->BillingInitials,
                "Gender" => $this->BillingGender,
                "BirthDate" => $this->BillingBirthDate
            ],
            null,
            'Person'
        );

        $this->setCustomVar(
            [
                "Street" => $this->BillingStreet,
                "HouseNumber" => isset($this->BillingHouseNumber) ? $this->BillingHouseNumber . ' ' : $this->BillingHouseNumber,
                "HouseNumberSuffix" => $this->BillingHouseNumberSuffix,
                "ZipCode" => $this->BillingPostalCode,
                "City" => $this->BillingCity,
                "Country" => $this->BillingCountry,
            ],
            null,
            'Address'
        );
        $this->setCustomVar("Phone", $this->BillingPhoneNumber, 'Phone');
        $this->setCustomVar("Email", $this->BillingEmail, 'Email');

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

        foreach (array_values($products['product']) as $pos => $p) {
            $this->setCustomVarsAtPosition(
                [
                    "Name" => $p["ArticleDescription"],
                    "Code" => $p["ArticleId"],
                    "Quantity" => $p["ArticleQuantity"],
                    "Price" =>$p["ArticleUnitprice"]
                ],
                $pos,
                'ProductLine'
            );
        }
        $i = count($products['product']);
        if (!empty($products['fee'])) {

            $this->setCustomVarsAtPosition(
                [
                    "Name"     => __('Payment fee', 'wc-buckaroo-bpe-gateway'),
                    "Code"     => $products['fee']["ArticleId"],
                    "Quantity" => $products['fee']["ArticleQuantity"],
                    "Price"    => $products['fee']["ArticleUnitprice"]
                ],
                $i,
                'ProductLine'
            );
            $i++;
        }

        if (!empty($this->cocNumber)) {
            $this->setCustomVar("ChamberOfCommerce", $this->cocNumber, 'Company');
        }

        if (!empty($this->companyName)) {
            $this->setCustomVarAtPosition(
                "Name", $this->companyName, $i, 'Company'
            );
            $i++;
        }
        $this->setCustomVarsAtPosition(
            [
                "Name"     => __('Shipping cost', 'wc-buckaroo-bpe-gateway'),
                "Code"     => __('Shipping', 'wc-buckaroo-bpe-gateway'),
                "Quantity" => '1',
                "Price"    => (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0')
            ],
            $i,
            'SubtotalLine'
        );

        $this->setCustomVar("IsInThreeGuarantee", $this->in3Version, '');

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
}
