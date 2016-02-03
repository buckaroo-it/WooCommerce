<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooPaySafeCard extends BuckarooPaymentMethod
{

    public function __construct()
    {
        $this->type = "paysafecard";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);

    }
}

?>