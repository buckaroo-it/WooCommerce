<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooPayPal extends BuckarooPaymentMethod
{
    public function __construct()
    {
        $this->type = "paypal";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);
    }
}

?>