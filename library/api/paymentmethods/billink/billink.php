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
    public $CustomerAccountNumber;
    public $CustomerIPAddress;
    public $Accept;

    public $B2B;
    public $CompanyCOCRegistration;
    public $CompanyName;
    public $CostCentre;
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

        $this->data['customVars'][$this->type]["Category"]["value"] = $this->getCategory();
        $this->data['customVars'][$this->type]["Category"]["group"] = 'BillingCustomer';
//        $this->data['customVars'][$this->type]["Category"][1]["value"] = 'Person';
//        $this->data['customVars'][$this->type]["Category"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["CareOf"][0]["value"] = 'TEST-hardcoded';
        $this->data['customVars'][$this->type]["CareOf"][0]["group"] = 'BillingCustomer';

        $this->data['customVars'][$this->type]["CareOf"][1]["value"] = 'TEST-hardcoded';
        $this->data['customVars'][$this->type]["CareOf"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Initials"][0]["value"] = $this->BillingInitials;
        $this->data['customVars'][$this->type]["Initials"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Initials"][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingInitials : $this->BillingInitials;
        $this->data['customVars'][$this->type]["Initials"][1]["group"] = 'ShippingCustomer';

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

        $this->data['customVars'][$this->type]["StreetNumber"][0]["value"] = $this->BillingHouseNumber . ' ';
        $this->data['customVars'][$this->type]["StreetNumber"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['StreetNumber'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingHouseNumber . ' ' : $this->BillingHouseNumber . ' ';
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
        $this->data['customVars'][$this->type]["Email"][1]["value"] = $this->BillingEmail;
        $this->data['customVars'][$this->type]["Email"][1]["group"] = 'ShippingCustomer';


        if( (isset($this->ShippingCountryCode) && in_array($this->ShippingCountryCode, ['NL', 'BE'])) || ( !isset($this->ShippingCountryCode) && in_array($this->BillingCountry, ['NL', 'BE'])) ){

            // Send parameters (Salutation, BirthDate, MobilePhone and Phone) if shipping country is NL || BE.
            $this->data['customVars'][$this->type]["Salutation"][0]["value"] = $this->BillingGender;
//            $this->data['customVars'][$this->type]["Salutation"][0]["value"] = ($this->BillingGender) == '1' ? 'Mr' : 'Mrs';
            $this->data['customVars'][$this->type]["Salutation"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["Salutation"][1]["value"] = $this->ShippingGender;
//            $this->data['customVars'][$this->type]["Salutation"][1]["value"] = ($this->ShippingGender) == '1' ? 'Mr' : 'Mrs';
            $this->data['customVars'][$this->type]["Salutation"][1]["group"] = 'ShippingCustomer';

            $this->data['customVars'][$this->type]["BirthDate"][0]["value"] = $this->BillingBirthDate;
            $this->data['customVars'][$this->type]["BirthDate"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["BirthDate"][1]["value"] = $this->BillingBirthDate;
            $this->data['customVars'][$this->type]["BirthDate"][1]["group"] = 'ShippingCustomer';

            $this->data['customVars'][$this->type]["MobilePhone"][0]["value"] = $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["MobilePhone"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["MobilePhone"][1]["value"] = $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["MobilePhone"][1]["group"] = 'ShippingCustomer';

        }

        if( (isset($this->ShippingCountryCode) && ($this->ShippingCountryCode == "FI")) || (!isset($this->ShippingCountryCode) && ($this->BillingCountry == "FI"))) {
            // Send parameter IdentificationNumber if country equals FI.
            $this->data['customVars'][$this->type]["IdentificationNumber"][0]["value"] = $this->IdentificationNumber;
            $this->data['customVars'][$this->type]["IdentificationNumber"][0]["group"] = 'BillingCustomer';
            // Send parameter IdentificationNumber if country equals FI.
            $this->data['customVars'][$this->type]["IdentificationNumber"][1]["value"] = $this->IdentificationNumber;
            $this->data['customVars'][$this->type]["IdentificationNumber"][1]["group"] = 'ShippingCustomer';
        }


        // if ($this->B2B == 'TRUE') {
        //     $this->data['customVars'][$this->type]['B2B'] = $this->B2B;
        //     $this->data['customVars'][$this->type]['CompanyCOCRegistration'] = $this->CompanyCOCRegistration;
        //     $this->data['customVars'][$this->type]['CompanyName'] = $this->CompanyName;
        //     $this->data['customVars'][$this->type]['CostCentre'] = $this->CostCentre;
        //     $this->data['customVars'][$this->type]['VatNumber'] = $this->VatNumber;
        // }

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

        $i = 1;
        foreach($products as $p) {
//            $this->data['customVars'][$this->type]["Description"][$i - 1]["value"] = $p["ArticleDescription"];
//            $this->data['customVars'][$this->type]["Description"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["value"] = $p["ArticleId"];
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitPriceIncl"][$i - 1]["value"] = $p["ArticleUnitpriceIncl"];
            $this->data['customVars'][$this->type]["GrossUnitPriceIncl"][$i - 1]["group"] = 'Article';
//            $this->data['customVars'][$this->type]["GrossUnitPriceExcl"][$i - 1]["value"] = $p["ArticleUnitpriceExcl"];
//            $this->data['customVars'][$this->type]["GrossUnitPriceExcl"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["value"] = isset($p["ArticleVatcategory"]) ? $p["ArticleVatcategory"] : 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["group"] = 'Article';
            $i++;
        }

//        $this->data['customVars'][$this->type]["Description"][$i]["value"] = 'Shipping Cost';
//        $this->data['customVars'][$this->type]["Description"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["Identifier"][$i]["value"] = 'shipping';
        $this->data['customVars'][$this->type]["Identifier"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["Quantity"][$i]["value"] = '1';
        $this->data['customVars'][$this->type]["Quantity"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["GrossUnitPriceIncl"][$i]["value"] = (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0');
        $this->data['customVars'][$this->type]["GrossUnitPriceIncl"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["VatPercentage"][$i]["value"] = (!empty($this->ShippingCostsTax) ? $this->ShippingCostsTax : '0');
        $this->data['customVars'][$this->type]["VatPercentage"][$i]["group"] = 'Article';

        if ($this->usenotification && !empty($customVars['Customeremail'])) {
            $this->data['services']['notification']['action'] = 'ExtraInfo';
            $this->data['services']['notification']['version'] = '1';
            $this->data['customVars']['notification']['NotificationType'] = $customVars['Notificationtype'];
            $this->data['customVars']['notification']['CommunicationMethod'] = 'email';
            $this->data['customVars']['notification']['RecipientEmail'] = $customVars['Customeremail'];
            $this->data['customVars']['notification']['RecipientFirstName'] = $customVars['CustomerFirstName'];
            $this->data['customVars']['notification']['RecipientLastName'] = $customVars['CustomerLastName'];
            $this->data['customVars']['notification']['RecipientGender'] = $customVars['Customergender'];
            if (!empty($customVars['Notificationdelay'])) {
                $this->data['customVars']['notification']['SendDatetime'] = $customVars['Notificationdelay'];
            }
        }

        return parent::Pay();
    }
}
