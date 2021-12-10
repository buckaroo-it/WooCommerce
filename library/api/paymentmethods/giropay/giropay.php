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
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = array()) {
        $this->setCustomVar('bic', $this->bic);
        return parent::Pay();
    }
}

?>