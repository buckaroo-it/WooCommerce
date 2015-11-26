<?php
require_once(dirname(__FILE__).'/../paymentmethod.php');
class BuckarooMisterCash extends BuckarooPaymentMethod{
    
    public function __construct()
    {
        $this->type = "bancontactmrcash";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode('MISTERCASH');
        
    }
}
