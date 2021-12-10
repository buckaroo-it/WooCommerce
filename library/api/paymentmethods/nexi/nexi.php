<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooNexi extends BuckarooPaymentMethod {

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "nexi";
        $this->version = 1;
    }
}

?>