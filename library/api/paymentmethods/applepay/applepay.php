<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooApplepay extends BuckarooPaymentMethod {
    protected $data;

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "applepay";
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay();
     */
    public function Pay($customVars = array()) {
        $this->data['services'][$this->type]['action'] = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;
        $this->data['customVars'][$this->type]['PaymentData'] = $customVars['PaymentData'];
        $this->data['customVars'][$this->type]['CustomerCardName'] = $customVars['CustomerCardName'];

        return parent::Pay();
    }
}