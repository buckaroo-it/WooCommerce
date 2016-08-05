<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooSofortbanking extends BuckarooPaymentMethod
{
    public function __construct()
    {
        $this->type = "sofortueberweisung";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);

    }
}