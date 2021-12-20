<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooEMaestro extends BuckarooPaymentMethod {

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "maestro";
        $this->version = 1;
    }
}

?>