<?php

require_once(dirname(__FILE__) . '/../response.php');

/**
 * @package Buckaroo
 */
class BuckarooPayGarantResponse extends BuckarooResponse {
    public $paylink = '';

    /**
     * @access protected
     */
    protected function _parseSoapResponseChild() {
        if (isset($this->_response->Services->Service->ResponseParameter) && isset($this->_response->Services->Service->Name)) {
            if ($this->_response->Services->Service->Name == 'paymentguarantee' && $this->_response->Services->Service->ResponseParameter->Name == 'paylink') {
                $this->paylink = $this->_response->Services->Service->ResponseParameter->_;
            }
        }
    }

    /**
     * @access protected
     */
    protected function _parsePostResponseChild() {

    }
}

