<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooPayPerEmail extends BuckarooPaymentMethod {

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "payperemail";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = Array()) {
        return null;
    }

    public function PaymentInvitation($customVars = Array()) {

        $this->data['services'][$this->type]['action'] = 'PaymentInvitation';
        $this->data['services'][$this->type]['version'] = $this->version;

        if (!empty($customVars['PaymentMethodsAllowed'])) {
            $this->data['customVars'][$this->type]['PaymentMethodsAllowed'] = $customVars['PaymentMethodsAllowed'];
        }

        if (isset($customVars['CustomerGender'])){
            $this->data['customVars'][$this->type]['customergender'] = $customVars['CustomerGender'];    
        }
        if (isset($customVars['CustomerFirstName'])){
            $this->data['customVars'][$this->type]['customerFirstName'] = $customVars['CustomerFirstName'];
        }
        if (isset($customVars['CustomerLastName'])){
            $this->data['customVars'][$this->type]['customerLastName'] = $customVars['CustomerLastName'];
        }
        if (isset($customVars['Customeremail'])){
            $this->data['customVars'][$this->type]['customeremail'] = $customVars['Customeremail'];
        }

        if (isset($customVars['merchantSendsEmail'])){
            $this->data['customVars'][$this->type]['merchantSendsEmail'] = $customVars['merchantSendsEmail'];
        }

        if (isset($customVars['ExpirationDate'])){
            $this->data['customVars'][$this->type]['ExpirationDate'] = $customVars['ExpirationDate'];
        }

        return $this->PayGlobal();
    }
}

?>