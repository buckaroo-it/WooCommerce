<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooP24 extends BuckarooPaymentMethod {

    public function __construct() {
        $this->type = "Przelewy24";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode('P24');

    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay();
     */
    public function Pay($customVars = array()) 
    {

        $this->data['services'][$this->type]['action'] = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['customVars'][$this->type]['CustomerEmail']['value'] = $customVars['Customeremail'];
        $this->data['customVars'][$this->type]['CustomerFirstName']['value'] = $customVars['CustomerFirstName'];
        $this->data['customVars'][$this->type]['CustomerLastName']['value'] = $customVars['CustomerLastName'];

        return $this->PayGlobal();

    }

}
