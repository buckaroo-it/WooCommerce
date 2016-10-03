<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooCreditCard extends BuckarooPaymentMethod
{

    public function __construct()
    {
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode('CREDITCARD');

    }

    public function Refund()
    {
        return parent::Refund();
    }

    public function Pay($customVars = Array())
    {
        $this->data['customVars']['servicesSelectableByClient'] = BuckarooConfig::get(
            'BUCKAROO_CREDITCARD_ALLOWED_CARDS'
        );
        $this->data['customVars']['continueOnIncomplete'] = 'RedirectToHTML';
        $this->data['services'] = array();
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
        return parent::PayGlobal();
    }
}

?>