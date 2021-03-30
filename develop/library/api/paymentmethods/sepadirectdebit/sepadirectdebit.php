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
    public function Pay($customVars = Array()) {
        return null;
    }

    /**
     * @access public
     * @param array $customVars
     * @return parent::Pay()
     */
    public function PayDirectDebit($customVars) {

        $this->data['customVars'][$this->type]['customeraccountname'] = $this->customeraccountname;
        $this->data['customVars'][$this->type]['CustomerBIC'] = $this->CustomerBIC;
        $this->data['customVars'][$this->type]['CustomerIBAN'] = $this->CustomerIBAN;

        if ($this->usecreditmanagment) {

            $this->data['services']['creditmanagement']['action'] = 'Invoice';
            $this->data['services']['creditmanagement']['version'] = '1';
            $this->data['customVars']['creditmanagement']['MaxReminderLevel'] = $customVars['MaxReminderLevel'];
            $this->data['customVars']['creditmanagement']['DateDue'] = $customVars['DateDue'];
            $this->data['customVars']['creditmanagement']['InvoiceDate'] = $customVars['InvoiceDate'];
            if (isset($customVars['CustomerCode'])) {
                $this->data['customVars']['creditmanagement']['CustomerCode'] = $customVars['CustomerCode'];
            }
            if (!empty($customVars['CompanyName'])) {
                $this->data['customVars']['creditmanagement']['CompanyName'] = $customVars['CompanyName'];
            }
            $this->data['customVars']['creditmanagement']['CustomerFirstName'] = $customVars['CustomerFirstName'];
            $this->data['customVars']['creditmanagement']['CustomerLastName'] = $customVars['CustomerLastName'];
            $this->data['customVars']['creditmanagement']['CustomerInitials'] = $customVars['CustomerInitials'];
            $this->data['customVars']['creditmanagement']['Customergender'] = $customVars['Customergender'];
            $this->data['customVars']['creditmanagement']['Customeremail'] = $customVars['Customeremail'];

            if (!empty($customVars['PaymentMethodsAllowed'])) {
                $this->data['customVars']['creditmanagement']['PaymentMethodsAllowed'] = $customVars['PaymentMethodsAllowed'];
            }

            if (isset($customVars['MobilePhoneNumber'])) {
                $this->data['customVars']['creditmanagement']['MobilePhoneNumber'] = $customVars['MobilePhoneNumber'];
                $this->data['customVars']['creditmanagement']['PhoneNumber'] = $customVars['MobilePhoneNumber'];
            }
            if (isset($customVars['PhoneNumber'])) {
                $this->data['customVars']['creditmanagement']['PhoneNumber'] = $customVars['PhoneNumber'];
            }
            if (isset($customVars['CustomerBirthDate'])) {
                $this->data['customVars']['creditmanagement']['CustomerBirthDate'] = $customVars['CustomerBirthDate'];
            }

            $this->data['customVars']['creditmanagement']['CustomerType'] = '0';
            $this->data['customVars']['creditmanagement']['AmountVat'] = $customVars['AmountVat'];

            foreach ($customVars['ADDRESS'] as $key => $adress) {

                $this->data['customVars']['creditmanagement'][$key]['value'] = $adress;
                $this->data['customVars']['creditmanagement'][$key]['group'] = 'address';

            }
        }

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
}

