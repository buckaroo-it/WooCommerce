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
    public function Pay($customVars = Array()) {

        $this->data['services'][$this->type]['action'] = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['customVars'][$this->type]['PaymentData'] = $customVars['PaymentData'];        

        if ($this->usenotification && !empty($customVars['Customeremail'])) {
            $this->data['services']['notification']['action'] = 'ExtraInfo';
            $this->data['services']['notification']['version'] = '1';
            $this->data['customVars']['notification']['NotificationType'] = $customVars['Notificationtype'];
            $this->data['customVars']['notification']['CommunicationMethod'] = 'email';
            $this->data['customVars']['notification']['RecipientEmail'] = $customVars['Customeremail'];
            $this->data['customVars']['notification']['RecipientFirstName'] = $customVars['CustomerFirstName'];
            $this->data['customVars']['notification']['RecipientLastName'] = $customVars['CustomerLastName'];
            $this->data['customVars']['notification']['RecipientGender'] = $customVars['Customergender'];
            if (!empty($customVars['Notificationdelay'])) {
                $this->data['customVars']['notification']['SendDatetime'] = $customVars['Notificationdelay'];
            }
        }

        return parent::Pay();
    }

    /**
     * @access public
     * @return callable parent::Refund();
     */
    public function Refund() {
        return parent::Refund();
    }

 
}