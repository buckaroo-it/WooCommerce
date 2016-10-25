<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooPayPal extends BuckarooPaymentMethod
{
    public function __construct()
    {
        $this->type = "paypal";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);
    }

    public function Pay($customVars = Array())
    {

        if ($this->sellerprotection && !empty($customVars['Customeremail'])){
            $this->data['services'][$this->type]['action2'] = 'extraInfo';
            $this->data['services'][$this->type]['version2'] = $this->version;

            $this->data['customVars'][$this->type]['Name'] = $customVars['CustomerLastName'];
            $this->data['customVars'][$this->type]['Street1'] = $customVars['ShippingStreet'] . ' '. $customVars['ShippingHouse'];
            $this->data['customVars'][$this->type]['CityName'] = $customVars['ShippingCity'];
            $this->data['customVars'][$this->type]['StateOrProvince'] = $customVars['StateOrProvince'];
            $this->data['customVars'][$this->type]['PostalCode'] = $customVars['ShippingPostalCode'];
            $this->data['customVars'][$this->type]['Country'] = $customVars['Country'];
            $this->data['customVars'][$this->type]['AddressOverride'] = 'TRUE';
        }

        if ($this->usenotification && !empty($customVars['Customeremail'])){
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