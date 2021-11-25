<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooKBC extends BuckarooPaymentMethod {
    public function __construct() {
        $this->type = "KBCPaymentButton";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = array())
    {
        $this->data['services'][$this->type]['action'] = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;
        return parent::Pay();
    }

    /**
     * @access public
     * @return callable parent::Refund();
     * @throws Exception
     */
    public function Refund() {
        return parent::Refund();
    }

    /**
     * @access public
     * @return callable parent::checkRefundData($data);
     * @param $data array
     * @throws Exception
     */
    public function checkRefundData($data) {
        return parent::checkRefundData($data);
    }
}
