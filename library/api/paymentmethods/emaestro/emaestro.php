<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooEMaestro extends BuckarooPaymentMethod
{
    public function __construct()
    {
        $this->type = "maestro";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);

    }
}

?>