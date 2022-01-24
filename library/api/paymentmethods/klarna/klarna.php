<?php
require_once(dirname(__FILE__).'/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooKlarna extends BuckarooPaymentMethod {
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
    public $AddressesDiffer;
    public $ShippingGender;
    public $ShippingInitials;
    public $ShippingLastName;
    public $ShippingBirthDate;
    public $ShippingStreet;
    public $ShippingHouseNumber;
    public $ShippingHouseNumberSuffix;
    public $ShippingPostalCode;
    public $ShippingCity;
    public $ShippingCountryCode;
    public $ShippingEmail;
    public $ShippingPhoneNumber;
    public $ShippingLanguage;
    public $ShippingCosts;
    public $ShippingCostsTax;
    public $CustomerIPAddress;
    public $Accept;
    public $BillingFirstName;
    public $ShippingFirstName;

    private $paymentFlow;
    private $billingCategory;
    private $shippingCategory;
    /**
     * @access public
     * @param string $type
     */
    public function __construct($type = 'klarna') {
        $this->type = $type;
        $this->version = '0';
    }

    public function setPaymnetFlow($paymentFlow){
        $this->paymentFlow = $paymentFlow;
    }
    public function getPaymnetFlow(){
        return $this->paymentFlow;
    }

    public function setBillingCategory($category){
        $this->billingCategory = $category;
    }

    public function getBillingCategory() {
        return $this->billingCategory;
    }

    public function setShippingCategory($category){
        $this->shippingCategory = $category;
    }

    public function getShippingCategory() {
        return $this->shippingCategory;
    }
    /**
     * @access public
     * @param array $products
     * @return callable parent::Pay();
     */
    public function paymentAction($products = array()) {

        $this->setServiceActionAndVersion($this->getPaymnetFlow());

        $billing = [
            "Category" => !empty($this->getBillingCategory()) ? 'B2B' : 'B2C',
            "FirstName" => $this->BillingFirstName,
            "LastName" => $this->BillingLastName,
            "Street" => $this->BillingStreet,
            "StreetNumber" => $this->BillingHouseNumber. ' ',
            "PostalCode" => $this->BillingPostalCode,
            "City" => $this->BillingCity,
            "Country" => $this->BillingCountry,
            "Email" => $this->BillingEmail,
            "Gender" => $this->BillingGender ?? 'Unknown',
            "Phone" => $this->BillingPhoneNumber
        ];
        $shipping = [
            "Category" => !empty($this->getShippingCategory()) ? 'B2B' : 'B2C',
            "FirstName" => $this->diffAddress($this->ShippingFirstName, $this->BillingFirstName),
            "LastName" => $this->diffAddress($this->ShippingLastName, $this->BillingLastName),
            "Street" => $this->diffAddress($this->ShippingStreet, $this->BillingStreet),
            "StreetNumber" => $this->diffAddress($this->ShippingHouseNumber, $this->BillingHouseNumber). ' ',
            "PostalCode" => $this->diffAddress($this->ShippingPostalCode, $this->BillingPostalCode),
            "City" => $this->diffAddress($this->ShippingCity, $this->BillingCity),
            "Country" => $this->diffAddress($this->ShippingCountryCode, $this->BillingCountry),
            "Email" => $this->BillingEmail,
            "Gender" => $this->diffAddress($this->ShippingGender, ($this->BillingGender ?? 'Unknown')),
            "Phone" => $this->BillingPhoneNumber
        ];



        if (!empty($this->BillingHouseNumberSuffix)) {
            $billing["StreetNumberAdditional"] = $this->BillingHouseNumberSuffix;
        } else {
            unset($this->BillingHouseNumberSuffix);
        }

        if (($this->AddressesDiffer == 'TRUE') && !empty($this->ShippingHouseNumberSuffix)) {
            $shipping['StreetNumberAdditional'] = $this->ShippingHouseNumberSuffix;
        } elseif ($this->AddressesDiffer !== 'TRUE' && !empty($this->BillingHouseNumberSuffix)) {
            $shipping['StreetNumberAdditional'] = $this->BillingHouseNumberSuffix; 
        } else {
            unset($this->ShippingHouseNumberSuffix);
        }


        $this->setCustomVarsAtPosition($billing, 0, 'BillingCustomer');
        $this->setCustomVarsAtPosition($shipping, 1, 'ShippingCustomer');

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

        foreach(array_values($products) as $pos => $p) {
            $vars = [
                    "Description"=> $p["ArticleDescription"],
                    "Identifier"=> $p["ArticleId"],
                    "Quantity"=> $p["ArticleQuantity"],
                    "GrossUnitprice"=> $p["ArticleUnitprice"],
                    "VatPercentage"=> isset($p["ArticleVatcategory"]) ? $p["ArticleVatcategory"] : 0,
                    "Url"=> $p["ProductUrl"]
            ];
            
            if (!empty($p['ImageUrl'])) {
                $vars["ImageUrl"] = $p["ImageUrl"];
            }


            $this->setCustomVarsAtPosition($vars, $pos, 'Article');
        }
        if (!empty($this->ShippingCosts)) {
            $this->setCustomVarsAtPosition(
                [
                    "Description"=> 'Shipping Cost',
                    "Identifier"=> 'shipping',
                    "Quantity"=> 1,
                    "GrossUnitprice"=> (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0'),
                    "VatPercentage"=> (!empty($this->ShippingCostsTax) ? $this->ShippingCostsTax : '0'),
                ],
                count($products),
                'Article'
            );
        }

        return parent::PayGlobal();
    }
    private function diffAddress($shippingField, $billingField)
    {
        if ($this->AddressesDiffer == 'TRUE') {
            return $shippingField;
        }
        return $billingField;
    }
}
