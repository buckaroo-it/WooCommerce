<?php
require_once(dirname(__FILE__).'/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooMisterCash extends BuckarooPaymentMethod {
    
    /**
     * @access public
     */
    public function __construct() {
        $this->type = "bancontactmrcash";
        $this->version = 1;
    }
}
