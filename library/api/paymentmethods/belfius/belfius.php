<?php
require_once dirname(__FILE__) . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooBelfius extends BuckarooPaymentMethod
{
    /**
     * @access public
     */
    public function __construct()
    {
        $this->type    = "belfius";
        $this->version = 0;
        $this->mode    = BuckarooConfig::getMode($this->type);

    }
}
