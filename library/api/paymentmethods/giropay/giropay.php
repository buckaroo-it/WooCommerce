<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooGiropay extends BuckarooPaymentMethod {
    public $bic = '';

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "giropay";
        $this->version = 2;
        $this->mode = BuckarooConfig::getMode($this->type);
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = array()) {
        $this->data['customVars'][$this->type]['bic'] = $this->bic;
        return parent::Pay();
    }
}

?>