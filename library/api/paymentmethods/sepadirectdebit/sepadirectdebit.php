<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooSepaDirectDebit extends BuckarooPaymentMethod {
    public $customeraccountname;
    public $CustomerBIC;
    public $CustomerIBAN;

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "sepadirectdebit";
        $this->version = '1';
        $this->mode = BuckarooConfig::getMode('SEPADIRECTDEBIT');
    }

    /**
     * @access public
     * @param array $customVars
     * @return void
     */
    public function Pay($customVars = array()) {
        return null;
    }

    /**
     * @access public
     * @param array $customVars
     * @return parent::Pay()
     */
    public function PayDirectDebit() {

        $this->data['customVars'][$this->type]['customeraccountname'] = $this->customeraccountname;
        $this->data['customVars'][$this->type]['CustomerBIC'] = $this->CustomerBIC;
        $this->data['customVars'][$this->type]['CustomerIBAN'] = $this->CustomerIBAN;

        return parent::Pay();
    }
}

