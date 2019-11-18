<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooCreditCard extends BuckarooPaymentMethod {

    public function __construct() {
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode('CREDITCARD');

    }

    /**
     * @access public
     * @return callable parent::Refund()
     */
    public function Refund() {
        $this->type = get_post_meta($this->orderId, '_wc_order_payment_issuer', true);
        return parent::Refund();
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::PayGlobal()
     */
    public function Pay($customVars = Array()) {
        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;
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
        // add the flag
        update_post_meta( $order->id, '_wc_order_authorized', 'yes' );

        return $this->PayGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::PayGlobal()
     */
    public function AuthorizeCC($customVars = Array(), $order) {

        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'Authorize';
        $this->data['services'][$this->type]['version'] = $this->version;

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

        // add the flag
        update_post_meta( $order->id, '_wc_order_authorized', 'yes' );

        return $this->PayGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::PayGlobal()
     */
    public function Capture($customVars = Array()) {

        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'Capture';
        $this->data['services'][$this->type]['version'] = $this->version;

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

        return $this->CaptureGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::PayGlobal()
     */
    public function PayEncrypt($customVars = Array()) {

        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'PayEncrypted';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['customVars'][$this->type]['EncryptedCardData'] = $customVars['CreditCardDataEncrypted'];

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

        return $this->PayGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::PayGlobal()
     */
    public function AuthorizeEncrypt($customVars = Array(), $order) {

        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'AuthorizeEncrypted';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['customVars'][$this->type]['EncryptedCardData'] = $customVars['CreditCardDataEncrypted'];

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

        // add the flag
        update_post_meta( $order->id, '_wc_order_authorized', 'yes' );

        return $this->PayGlobal();
    }

}

?>