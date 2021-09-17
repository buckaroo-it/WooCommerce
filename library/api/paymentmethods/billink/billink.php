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
        $this->mode = BuckarooConfig::getMode($this->type);
    }

    /**
     * @access public
     * @param array $customVars
     * @return void
     */
    public function Pay($customVars = Array()) {
        return null;
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
    public function PayOrAuthorizeBillink($products = Array(), $action = 'Pay') {

        $this->data['customVars'][$this->type]["Category"][0]["value"] = $this->getCategory();
        $this->data['customVars'][$this->type]["Category"][0]["group"] = 'BillingCustomer';

        $this->data['customVars'][$this->type]["CareOf"][0]["value"] = trim($this->getBillingFirstName() . ' ' . $this->BillingLastName);
        $this->data['customVars'][$this->type]["CareOf"][0]["group"] = 'BillingCustomer';

        $this->data['customVars'][$this->type]["CareOf"][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? trim($this->ShippingFirstName . ' ' . $this->ShippingLastName) : $this->data['customVars'][$this->type]["CareOf"][0]["value"];
        $this->data['customVars'][$this->type]["CareOf"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Initials"][0]["value"] = $this->BillingInitials;
        $this->data['customVars'][$this->type]["Initials"][0]["group"] = 'BillingCustomer';

        $this->data['customVars'][$this->type]['FirstName'][0]["value"] = $this->getBillingFirstName();
        $this->data['customVars'][$this->type]["FirstName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['FirstName'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingFirstName : $this->getBillingFirstName();
        $this->data['customVars'][$this->type]["FirstName"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["LastName"][0]["value"] = $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['LastName'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingLastName : $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Street"][0]["value"] = $this->BillingStreet;
        $this->data['customVars'][$this->type]["Street"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['Street'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingStreet : $this->BillingStreet;
        $this->data['customVars'][$this->type]["Street"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["StreetNumber"][0]["value"] = $this->BillingHouseNumber;
        $this->data['customVars'][$this->type]["StreetNumber"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['StreetNumber'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingHouseNumber : $this->BillingHouseNumber;
        $this->data['customVars'][$this->type]["StreetNumber"][1]["group"] = 'ShippingCustomer';

        if(!empty($this->BillingHouseNumberSuffix)){
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][0]["value"] = $this->BillingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][0]["group"] = 'BillingCustomer';
        }

        if(!empty($this->BillingHouseNumberSuffix) || !empty($this->ShippingHouseNumberSuffix)){
            $this->data['customVars'][$this->type]['StreetNumberAdditional'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingHouseNumberSuffix : $this->BillingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][1]["group"] = 'ShippingCustomer';
        }

        $this->data['customVars'][$this->type]["PostalCode"][0]["value"] = $this->BillingPostalCode;
        $this->data['customVars'][$this->type]["PostalCode"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['PostalCode'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingPostalCode : $this->BillingPostalCode;
        $this->data['customVars'][$this->type]["PostalCode"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["City"][0]["value"] = $this->BillingCity;
        $this->data['customVars'][$this->type]["City"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['City'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingCity : $this->BillingCity;
        $this->data['customVars'][$this->type]["City"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Country"][0]["value"] = $this->BillingCountry;
        $this->data['customVars'][$this->type]["Country"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['Country'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingCountryCode : $this->BillingCountry;
        $this->data['customVars'][$this->type]["Country"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Email"][0]["value"] = $this->BillingEmail;
        $this->data['customVars'][$this->type]["Email"][0]["group"] = 'BillingCustomer';

        $this->data['customVars'][$this->type]["MobilePhone"][0]["value"] = $this->BillingPhoneNumber;
        $this->data['customVars'][$this->type]["MobilePhone"][0]["group"] = 'BillingCustomer';

        if ($this->B2B) {
            $this->data['customVars'][$this->type]["ChamberOfCommerce"][0]["value"] = $this->CompanyCOCRegistration;
            $this->data['customVars'][$this->type]["ChamberOfCommerce"][0]["group"] = 'BillingCustomer';

            if (!empty($this->VatNumber)){
                $this->data['customVars'][$this->type]["VATNumber"][0]["value"] = $this->VatNumber;
                $this->data['customVars'][$this->type]["VATNumber"][0]["group"] = 'BillingCustomer';
            }
        } else {
            $this->data['customVars'][$this->type]["Salutation"][0]["value"] = $this->BillingGender;
            $this->data['customVars'][$this->type]["Salutation"][0]["group"] = 'BillingCustomer';

            $this->data['customVars'][$this->type]["BirthDate"][0]["value"] = $this->BillingBirthDate;
            $this->data['customVars'][$this->type]["BirthDate"][0]["group"] = 'BillingCustomer';
        }

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

        $i = 0;
        foreach($products as $p) {
            $this->data['customVars'][$this->type]["Description"][$i]["value"]    = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["Description"][$i]["group"]    = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i]["value"] = $p["ArticleId"];
            $this->data['customVars'][$this->type]["Identifier"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitPriceIncl"][$i]["value"] = $p["ArticleUnitpriceIncl"];
            $this->data['customVars'][$this->type]["GrossUnitPriceIncl"][$i]["group"] = 'Article';
            //if float then will be "An unhandled exception occurred, please contact Buckaroo Technical Support." from gateway
            $this->data['customVars'][$this->type]["VatPercentage"][$i]["value"] = isset($p["ArticleVatcategory"]) ? intval($p["ArticleVatcategory"]) : 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i]["group"] = 'Article';
            $i++;
        }

        $this->data['customVars'][$this->type]["Description"][$i]["value"]    = 'Shipping Cost';
        $this->data['customVars'][$this->type]["Description"][$i]["group"]    = 'Article';
        $this->data['customVars'][$this->type]["Identifier"][$i]["value"] = 'shipping';
        $this->data['customVars'][$this->type]["Identifier"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["Quantity"][$i]["value"] = '1';
        $this->data['customVars'][$this->type]["Quantity"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["GrossUnitPriceIncl"][$i]["value"] = (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0');
        $this->data['customVars'][$this->type]["GrossUnitPriceIncl"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["VatPercentage"][$i]["value"] = (!empty($this->ShippingCostsTax) ? $this->ShippingCostsTax : '0');
        $this->data['customVars'][$this->type]["VatPercentage"][$i]["group"] = 'Article';

        return parent::Pay();
    }
}
