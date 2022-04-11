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
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = array()) 
    {
        if(isset($customVars['PayPalOrderId'])) {
            $this->setCustomVar('PayPalOrderId', $customVars['PayPalOrderId']);
            return parent::Pay();
        }

        if ($this->sellerprotection) {
            $this->setService('action2', 'extraInfo');
            $this->setService('version2', $this->version);

            $this->setCustomVar(
                [
                'Name'=>$customVars['CustomerLastName'],
                'Street1'=>$customVars['ShippingStreet'] . ' '. $customVars['ShippingHouse'],
                'CityName'=>$customVars['ShippingCity'],
                'StateOrProvince'=>$customVars['StateOrProvince'],
                'PostalCode'=>$customVars['ShippingPostalCode'],
                'Country'=>$customVars['Country'],
                'AddressOverride'=>'TRUE'
                ]
            );
            
        }

        return parent::Pay();
    }
}

?>