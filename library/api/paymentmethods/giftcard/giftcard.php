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
    public function Pay($customVars = array()) {

        if(empty($customVars['servicesSelectableByClient'])){
            $this->data['customVars']['servicesSelectableByClient'] = BuckarooConfig::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS');
        } else {
            $this->data['customVars']['servicesSelectableByClient'] = $customVars['servicesSelectableByClient'];
        }

        $this->data['customVars']['continueOnIncomplete'] = 'RedirectToHTML';
        $this->data['services'] = array();        
        return parent::PayGlobal();
    }
}

?>