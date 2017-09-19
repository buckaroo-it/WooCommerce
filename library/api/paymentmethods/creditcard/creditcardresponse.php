<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once(dirname(__FILE__) . '/../response.php');

/**
 * @package Buckaroo
 */
class BuckarooCreditCardResponse extends BuckarooResponse {
    public $cardNumberEnding = '';
    
    /**
     * @access protected
     */
    protected function _parseSoapResponseChild() {

    }

    /**
     * @access protected
     */
    protected function _parsePostResponseChild() {
        if (isset($_POST['brq_service_' . $this->payment_method . '_CardNumberEnding'])) {
            $this->cardNumberEnding = $_POST['brq_service_' . $this->payment_method . '_CardNumberEnding'];
        }
    }
}

?>
