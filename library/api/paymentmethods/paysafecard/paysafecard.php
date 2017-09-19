<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooPaySafeCard extends BuckarooPaymentMethod {

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "paysafecard";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);
    }
}

?>