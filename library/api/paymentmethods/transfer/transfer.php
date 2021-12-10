<?php

require_once(dirname(__FILE__).'/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooTransfer extends BuckarooPaymentMethod {
    public function __construct() {
        $this->type = "transfer";
        $this->version = 1;
    }

    public function Pay($customVars = array()) {
        return null;
    }
    
    public function PayTransfer($customVars) {
        
        if (isset($customVars['CustomerGender']))
            $this->data['customVars'][$this->type]['customergender'] = $customVars['CustomerGender'];    
        if (isset($customVars['CustomerFirstName']))
            $this->data['customVars'][$this->type]['customerFirstName'] = $customVars['CustomerFirstName'];
        if (isset($customVars['CustomerLastName']))
            $this->data['customVars'][$this->type]['customerLastName'] = $customVars['CustomerLastName'];
        if (isset($customVars['Customeremail']))
            $this->data['customVars'][$this->type]['customeremail'] = $customVars['Customeremail'];
        if (isset($customVars['DateDue']))        
            $this->data['customVars'][$this->type]['DateDue'] = $customVars['DateDue'];
        if (isset($customVars['CustomerCountry']))        
            $this->data['customVars'][$this->type]['customercountry'] = $customVars['CustomerCountry'];
        $this->data['customVars'][$this->type]['SendMail'] = $customVars['SendMail'];      
        
        return parent::Pay($customVars);
    }
}
