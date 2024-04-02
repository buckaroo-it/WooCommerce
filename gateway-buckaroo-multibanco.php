<?php

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Multibanco extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_multibanco';
        $this->title                  = 'Multibanco';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Multibanco";
        $this->setIcon('svg/multibanco.svg', 'svg/multibanco.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
}
