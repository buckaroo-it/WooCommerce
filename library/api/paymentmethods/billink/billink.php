<?php

class BuckarooBillink extends BuckarooPaymentMethod
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
    public $AddressesDiffer;
    public $ShippingGender;
    public $ShippingInitials;
    public $ShippingLastName;
    public $ShippingStreet;
    public $ShippingHouseNumber;
    public $ShippingHouseNumberSuffix;
    public $ShippingPostalCode;
    public $ShippingCity;
    public $ShippingCountryCode;
    public $ShippingEmail;
    public $ShippingPhoneNumber;
    public $ShippingCosts;
    public $CustomerIPAddress;
    public $Accept;

    public $B2B;
    public $Company;
    public $CompanyCOCRegistration;
    public $VatNumber;

    private $category;
    private $billingFirstName;
    /**
     * @access public
     * @param string $type
     */
    public function __construct($type = 'Billink')
    {
        $this->type = $type;
        $this->version = '1';
    }

    /**
     * @access public
     * @param array $customVars
     * @return void
     */
    public function Pay($customVars = array()) {
        return null;
    }

    public function setCompany($company){
        $this->Company = $company;
    }

    public function getCompany(){
        return $this->Company;
    }

    public function setCategory($category){
        $this->category = $category;
    }

    public function getCategory(){
        return $this->category;
    }

    public function setBillingFirstName($billingFirstName) {
        $this->billingFirstName = $billingFirstName;
    }

    public function getBillingFirstName(){
        return $this->billingFirstName;
    }
    /**
     * @access public
     * @param array $products
     * @return callable parent::Pay();
     */
    public function PayOrAuthorizeBillink($products = array(), $action = 'Pay') {

        $billing = [
            "Category" => $this->getCategory(),
            "Initials" => $this->BillingInitials,
            "FirstName" => $this->getBillingFirstName(),
            "LastName" => $this->BillingLastName,
            "Street" => $this->BillingStreet,
            "StreetNumber" => $this->BillingHouseNumber,
            "PostalCode" => $this->BillingPostalCode,
            "City" => $this->BillingCity,
            "Country" => $this->BillingCountry,
            "Email" => $this->BillingEmail,
            "MobilePhone" => $this->BillingPhoneNumber
        ];
        $shipping = [
            "FirstName" => $this->diffAddress($this->ShippingInitials, $this->getBillingFirstName()),
            "LastName" => $this->diffAddress($this->ShippingLastName, $this->BillingLastName),
            "Street" => $this->diffAddress($this->ShippingStreet, $this->BillingStreet),
            "StreetNumber" => $this->diffAddress($this->ShippingHouseNumber, $this->BillingHouseNumber),
            "PostalCode" => $this->diffAddress($this->ShippingPostalCode, $this->BillingPostalCode),
            "City" => $this->diffAddress($this->ShippingCity, $this->BillingCity),
            "Country" => $this->diffAddress($this->ShippingCountryCode, $this->BillingCountry),
        ];

        if ($this->B2B) {
            $billingCareOf = $shippingCareOf = $this->getCompany();
        } else {
            $billingCareOf = trim($this->getBillingFirstName() . ' ' . $this->BillingLastName);
            $shippingCareOf =$this->diffAddress(trim($this->ShippingFirstName . ' ' . $this->ShippingLastName), $billingCareOf);
        }

        $billing['CareOf'] = $billingCareOf;
        $shipping['CareOf'] = $shippingCareOf;


        if(!empty($this->BillingHouseNumberSuffix)){
            $billing['StreetNumberAdditional'] = $this->BillingHouseNumberSuffix;
        }

        if(!empty($this->BillingHouseNumberSuffix) || !empty($this->ShippingHouseNumberSuffix)){
            if(!empty($this->diffAddress($this->ShippingHouseNumberSuffix, $this->BillingHouseNumberSuffix))){
                $shipping['StreetNumberAdditional'] =  $this->diffAddress($this->ShippingHouseNumberSuffix, $this->BillingHouseNumberSuffix);
            }
        }
        

        if ($this->B2B) {
            $billing['ChamberOfCommerce'] = $this->CompanyCOCRegistration;

            if (!empty($this->VatNumber)){
                $billing['VATNumber'] = $this->VatNumber;
            }
        } else {
            $billing['Salutation'] = $this->BillingGender;
            $billing['BirthDate'] = $this->BillingBirthDate;
        }

        $products = $this->PayOrAuthorizeCommon($products, $billing, $shipping);

        foreach(array_values($products) as $pos => $p) {
            $this->setCustomVarsAtPosition(
                [
                    "Description"=> $p["ArticleDescription"],
                    "Identifier"=> $p["ArticleId"],
                    "Quantity"=> $p["ArticleQuantity"],
                    "GrossUnitPriceIncl"=> $p["ArticleUnitpriceIncl"],
                    "VatPercentage"=> isset($p["ArticleVatcategory"]) ? intval($p["ArticleVatcategory"]) : 0,
                ],
                $pos,
                'Article'
            );
        }

        $this->setCustomVarsAtPosition(
            [
                "Description"=> 'Shipping Cost',
                "Identifier"=> 'shipping',
                "Quantity"=> 1,
                "GrossUnitPriceIncl"=> (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0'),
                "VatPercentage"=> (!empty($this->ShippingCostsTax) ? $this->ShippingCostsTax : '0'),
            ],
            count($products),
            'Article'
        );
        return parent::Pay();
    }
    private function diffAddress($shippingField, $billingField)
    {
        if ($this->AddressesDiffer == 'TRUE') {
            return $shippingField;
        }
        return $billingField;
    }
}
