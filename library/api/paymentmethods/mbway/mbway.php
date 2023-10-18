<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooMBWay extends BuckarooPaymentMethod {
    public function __construct() {
        $this->type = "MBWay";
    }
}
