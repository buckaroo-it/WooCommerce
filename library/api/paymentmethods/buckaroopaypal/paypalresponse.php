<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once(dirname(__FILE__) . '/../response.php');

class BuckarooPayPalResponse extends BuckarooResponse
{
    public $payerEmail;
    public $payerCountry;
    public $payerStatus;
    public $payerFirstname;
    public $payerLastname;
    public $paypalTransactionID;

    protected function _parseSoapResponseChild()
    {
        $this->payerEmail = '';
        $this->payerCountry = '';
        $this->payerStatus = '';
        $this->payerFirstname = '';
        $this->payerLastname = '';
        $this->paypalTransactionID = '';
    }

    protected function _parsePostResponseChild()
    {
        if (isset($_POST['brq_service_paypal_payerEmail'])) {
            $this->payerEmail = $_POST['brq_service_paypal_payerEmail'];
        }
        if (isset($_POST['brq_service_paypal_payerCountry'])) {
            $this->payerCountry = $_POST['brq_service_paypal_payerCountry'];
        }
        if (isset($_POST['brq_service_paypal_payerStatus'])) {
            $this->payerStatus = $_POST['brq_service_paypal_payerStatus'];
        }
        if (isset($_POST['brq_service_paypal_payerFirstname'])) {
            $this->payerFirstname = $_POST['brq_service_paypal_payerFirstname'];
        }
        if (isset($_POST['brq_service_paypal_payerLastname'])) {
            $this->payerLastname = $_POST['brq_service_paypal_payerLastname'];
        }
        if (isset($_POST['brq_service_paypal_paypalTransactionID'])) {
            $this->paypalTransactionID = $_POST['brq_service_paypal_paypalTransactionID'];
        }
    }

}

?>
