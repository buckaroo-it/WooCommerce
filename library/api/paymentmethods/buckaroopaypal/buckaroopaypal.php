<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooPayPal extends BuckarooPaymentMethod {
    public function __construct()
    {
        $this->type = "paypal";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = array()) 
    {
        if ($this->sellerprotection) {
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

        return parent::Pay();
    }
}

?>