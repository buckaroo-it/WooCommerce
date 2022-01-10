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
    public function Pay($customVars = array()) {
        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;
        // add the flag
        update_post_meta( $this->orderId, '_wc_order_authorized', 'yes' );

        return $this->PayGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::PayGlobal()
     */
    public function AuthorizeCC($customVars, $order) {

        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'Authorize';
        $this->data['services'][$this->type]['version'] = $this->version;

        // add the flag
        update_post_meta( $order->get_id(), '_wc_order_authorized', 'yes' );

        return $this->PayGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::PayGlobal()
     */
    public function Capture($customVars = array()) {

        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'Capture';
        $this->data['services'][$this->type]['version'] = $this->version;


        return $this->CaptureGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::PayGlobal()
     */
    public function PayEncrypt($customVars = array()) {

        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'PayEncrypted';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['customVars'][$this->type]['EncryptedCardData'] = $customVars['CreditCardDataEncrypted'];



        return $this->PayGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::PayGlobal()
     */
    public function AuthorizeEncrypt($customVars, $order) {

        $this->type = $customVars['CreditCardIssuer'];
        $this->version = 0;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'AuthorizeEncrypted';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['customVars'][$this->type]['EncryptedCardData'] = $customVars['CreditCardDataEncrypted'];

        // add the flag
        update_post_meta( $order->get_id(), '_wc_order_authorized', 'yes' );

        return $this->PayGlobal();
    }
}
