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
        $this->mode = BuckarooConfig::getMode('Klarna');
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
    public function paymentAction($products = Array()) {

        $this->data['services'][$this->type]['action'] = $this->getPaymnetFlow();
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['customVars'][$this->type]["Category"][0]["value"] = !empty($this->getBillingCategory()) ? 'B2B' : 'B2C';
        $this->data['customVars'][$this->type]["Category"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Category"][1]["value"] = !empty($this->getShippingCategory()) ? 'B2B' : 'B2C';
        $this->data['customVars'][$this->type]["Category"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["FirstName"][0]["value"] = $this->BillingFirstName;
        $this->data['customVars'][$this->type]["FirstName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['FirstName'][1]["value"] = ($this->AddressesDiffer == 'TRUE' && !empty($this->ShippingFirstName)) ? $this->ShippingFirstName : $this->BillingFirstName;
        $this->data['customVars'][$this->type]["FirstName"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["LastName"][0]["value"] = $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['LastName'][1]["value"] = ($this->AddressesDiffer == 'TRUE' && !empty($this->ShippingLastName)) ? $this->ShippingLastName : $this->BillingLastName;
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
        } else {
            unset($this->BillingHouseNumberSuffix);
        }

        if(($this->AddressesDiffer == 'TRUE') && !empty($this->ShippingHouseNumberSuffix)){
            $this->data['customVars'][$this->type]['StreetNumberAdditional'][1]["value"] = $this->ShippingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][1]["group"] = 'ShippingCustomer';
        } elseif ($this->AddressesDiffer !== 'TRUE' && !empty($this->BillingHouseNumberSuffix)) {
            $this->data['customVars'][$this->type]['StreetNumberAdditional'][1]["value"] = $this->BillingHouseNumberSuffix; //($this->AddressesDiffer == 'TRUE') ? $this->ShippingHouseNumberSuffix : $this->BillingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][1]["group"] = 'ShippingCustomer';
        } else {
            unset($this->ShippingHouseNumberSuffix);
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

        $this->data['customVars'][$this->type]["Gender"][0]["value"] = $this->BillingGender ?? 'Unknown';
        $this->data['customVars'][$this->type]["Gender"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Gender"][1]["value"] = $this->ShippingGender ?? $this->AddressesDiffer == 'TRUE' ? 'Unknown' : $this->data['customVars'][$this->type]["Gender"][0]["value"];
        $this->data['customVars'][$this->type]["Gender"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Phone"][0]["value"] = $this->BillingPhoneNumber;
        $this->data['customVars'][$this->type]["Phone"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Phone"][1]["value"] = $this->BillingPhoneNumber;
        $this->data['customVars'][$this->type]["Phone"][1]["group"] = 'ShippingCustomer';

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
            $this->data['customVars'][$this->type]["Description"][$i - 1]["value"] = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["Description"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["value"] = $p["ArticleId"];
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["value"] = isset($p["ArticleVatcategory"]) ? $p["ArticleVatcategory"] : 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Url"][$i - 1]["value"] = $p["ProductUrl"];
            $this->data['customVars'][$this->type]["Url"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["ImageUrl"][$i - 1]["value"] = $p["ImageUrl"];
            $this->data['customVars'][$this->type]["ImageUrl"][$i - 1]["group"] = 'Article';
            $i++;
        }
        if (!empty($this->ShippingCosts)) {
            $this->data['customVars'][$this->type]["Description"][$i]["value"] = 'Shipping Cost';
            $this->data['customVars'][$this->type]["Description"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i]["value"] = 'shipping';
            $this->data['customVars'][$this->type]["Identifier"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i]["value"] = '1';
            $this->data['customVars'][$this->type]["Quantity"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i]["value"] = (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0');
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i]["value"] = (!empty($this->ShippingCostsTax) ? $this->ShippingCostsTax : '0');
            $this->data['customVars'][$this->type]["VatPercentage"][$i]["group"] = 'Article';
        }

        if ($this->usenotification && !empty($customVars['Customeremail'])) {
            $this->data['services']['notification']['action'] = 'ExtraInfo';
            $this->data['services']['notification']['version'] = '1';
            $this->data['customVars']['notification']['NotificationType'] = $customVars['Notificationtype'];
            $this->data['customVars']['notification']['CommunicationMethod'] = 'email';
            $this->data['customVars']['notification']['RecipientEmail'] = $customVars['Customeremail'];
            $this->data['customVars']['notification']['RecipientFirstName'] = $customVars['CustomerFirstName'];
            $this->data['customVars']['notification']['RecipientLastName'] = $customVars['CustomerLastName'];
            if (!empty($customVars['Notificationdelay'])) {
                $this->data['customVars']['notification']['SendDatetime'] = $customVars['Notificationdelay'];
            }
        }

        return parent::PayGlobal();
    }

    public function checkRefundData($data){
        return parent::checkRefundData($data);
    }
}

?>
