<?php
require_once(dirname(__FILE__).'/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooAfterPay extends BuckarooPaymentMethod {
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

    /**
     * @access public
     * @param string $type
     */
    public function __construct($type = 'afterpaydigiaccept') {
        $this->type = $type;
        $this->version = '1';
        $this->mode = BuckarooConfig::getMode('AFTERPAY');
    }

    /**
     * @access public
     * @param array $customVars
     * @return void
     */
    public function Pay($customVars = Array()) {
        return null;
    }
    
    /**
     * @access public
     * @param array $products
     * @return callable parent::Pay();
     */
    public function PayAfterpay($products = Array()) {
        $this->data['customVars'][$this->type]['BillingGender'] = $this->BillingGender;
        $this->data['customVars'][$this->type]['BillingInitials'] = $this->BillingInitials;
        $this->data['customVars'][$this->type]['BillingLastName'] = $this->BillingLastName;
        $this->data['customVars'][$this->type]['BillingBirthDate'] = $this->BillingBirthDate;
        $this->data['customVars'][$this->type]['BillingStreet'] = $this->BillingStreet;
        $this->data['customVars'][$this->type]['BillingHouseNumber'] = $this->BillingHouseNumber;
        $this->data['customVars'][$this->type]['BillingHouseNumberSuffix'] = $this->BillingHouseNumberSuffix;
        $this->data['customVars'][$this->type]['BillingPostalCode'] = $this->BillingPostalCode;
        $this->data['customVars'][$this->type]['BillingCity'] = $this->BillingCity;
        $this->data['customVars'][$this->type]['BillingCountry'] = $this->BillingCountry;
        $this->data['customVars'][$this->type]['BillingEmail'] = $this->BillingEmail;
        $this->data['customVars'][$this->type]['BillingPhoneNumber'] = $this->BillingPhoneNumber;
        $this->data['customVars'][$this->type]['BillingLanguage'] = $this->BillingLanguage;
        $this->data['customVars'][$this->type]['AddressesDiffer'] = $this->AddressesDiffer;
        if ($this->AddressesDiffer == 'TRUE') {
            $this->data['customVars'][$this->type]['ShippingGender'] = $this->ShippingGender;
            $this->data['customVars'][$this->type]['ShippingInitials'] = $this->ShippingInitials;
            $this->data['customVars'][$this->type]['ShippingLastName'] = $this->ShippingLastName;
            $this->data['customVars'][$this->type]['ShippingBirthDate'] = $this->ShippingBirthDate;
            $this->data['customVars'][$this->type]['ShippingStreet'] = $this->ShippingStreet;
            $this->data['customVars'][$this->type]['ShippingHouseNumber'] = $this->ShippingHouseNumber;
            $this->data['customVars'][$this->type]['ShippingHouseNumberSuffix'] = $this->ShippingHouseNumberSuffix;
            $this->data['customVars'][$this->type]['ShippingPostalCode'] = $this->ShippingPostalCode;
            $this->data['customVars'][$this->type]['ShippingCity'] = $this->ShippingCity;
            $this->data['customVars'][$this->type]['ShippingCountryCode'] = $this->ShippingCountryCode;
            $this->data['customVars'][$this->type]['ShippingEmail'] = $this->ShippingEmail;
            $this->data['customVars'][$this->type]['ShippingPhoneNumber'] = $this->ShippingPhoneNumber;
            $this->data['customVars'][$this->type]['ShippingLanguage'] = $this->ShippingLanguage;
        }
        if ($this->B2B == 'TRUE') {
            $this->data['customVars'][$this->type]['B2B'] = $this->B2B;
            $this->data['customVars'][$this->type]['CompanyCOCRegistration'] = $this->CompanyCOCRegistration;
            $this->data['customVars'][$this->type]['CompanyName'] = $this->CompanyName;
            $this->data['customVars'][$this->type]['CostCentre'] = $this->CostCentre;
            $this->data['customVars'][$this->type]['VatNumber'] = $this->VatNumber;
        }
        $this->data['customVars'][$this->type]['ShippingLanguage'] = $this->ShippingLanguage;
        if ($this->type == 'afterpayacceptgiro') {
            $this->data['customVars'][$this->type]['CustomerAccountNumber'] = $this->CustomerAccountNumber;
        }
        if ($this->ShippingCosts > 0) {
            $this->data['customVars'][$this->type]['ShippingCosts'] = $this->ShippingCosts;
        }
        $this->data['customVars'][$this->type]['CustomerIPAddress'] = $this->CustomerIPAddress;
        $this->data['customVars'][$this->type]['Accept'] = $this->Accept;
        $i = 1;
        foreach($products as $p) {
            $this->data['customVars'][$this->type]["ArticleDescription"][$i - 1]["value"] = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["ArticleDescription"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleId"][$i - 1]["value"] = $p["ArticleId"];
            $this->data['customVars'][$this->type]["ArticleId"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleQuantity"][$i - 1]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["ArticleQuantity"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleUnitprice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["ArticleUnitprice"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleVatcategory"][$i - 1]["value"] = $p["ArticleVatcategory"];
            $this->data['customVars'][$this->type]["ArticleVatcategory"][$i - 1]["group"] = $i;
            $i++;
        }

        $this->data['customVars'][$this->type]['ShippingGender'] = $this->ShippingGender;
        $this->data['customVars'][$this->type]['ShippingInitials'] = $this->ShippingInitials;
        $this->data['customVars'][$this->type]['ShippingLastName'] = $this->ShippingLastName;
        $this->data['customVars'][$this->type]['ShippingBirthDate'] = $this->ShippingBirthDate;
        $this->data['customVars'][$this->type]['ShippingStreet'] = $this->ShippingStreet;
        $this->data['customVars'][$this->type]['ShippingHouseNumber'] = $this->ShippingHouseNumber;
        $this->data['customVars'][$this->type]['ShippingHouseNumberSuffix'] = $this->ShippingHouseNumberSuffix;
        $this->data['customVars'][$this->type]['ShippingPostalCode'] = $this->ShippingPostalCode;
        $this->data['customVars'][$this->type]['ShippingCity'] = $this->ShippingCity;
        $this->data['customVars'][$this->type]['ShippingCountryCode'] = $this->ShippingCountryCode;
        $this->data['customVars'][$this->type]['ShippingEmail'] = $this->ShippingEmail;
        $this->data['customVars'][$this->type]['ShippingPhoneNumber'] = $this->ShippingPhoneNumber;
        $this->data['customVars'][$this->type]['ShippingLanguage'] = $this->ShippingLanguage;

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

?>
