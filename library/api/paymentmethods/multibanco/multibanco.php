<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooMultibanco extends BuckarooPaymentMethod {
    public function __construct() {
        $this->type = "Multibanco";
    }
}
