<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooGiftCard extends BuckarooPaymentMethod {
    public $cardtype = '';

    /**
     * @access public
     * @return void
     */
    public function __construct() {
        $this->mode = BuckarooConfig::getMode('GIFTCARD');
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = Array()) {

        if(empty($customVars['servicesSelectableByClient'])){
            $this->data['customVars']['servicesSelectableByClient'] = BuckarooConfig::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS');
        } else {
            $this->data['customVars']['servicesSelectableByClient'] = $customVars['servicesSelectableByClient'];
        }

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