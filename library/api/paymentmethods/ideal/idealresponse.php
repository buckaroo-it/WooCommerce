<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once(dirname(__FILE__) . '/../response.php');

class BuckarooIDealResponse extends BuckarooResponse
{
    public $consumerIssuer;
    public $consumerName;
    public $consumerAccountNumber;
    public $consumerCity;

    protected function _parseSoapResponseChild()
    {

    }


    protected function _parsePostResponseChild()
    {
        if (isset($_POST['brq_service_ideal_consumerIssuer'])) {
            $this->consumerIssuer = $_POST['brq_service_ideal_consumerIssuer'];
        }
        if (isset($_POST['brq_service_ideal_consumerName'])) {
            $this->consumerName = $_POST['brq_service_ideal_consumerName'];
        }
        if (isset($_POST['brq_service_ideal_consumerAccountNumber'])) {
            $this->consumerAccountNumber = $_POST['brq_service_ideal_consumerAccountNumber'];
        }
        if (isset($_POST['brq_service_ideal_consumerCity'])) {
            $this->consumerCity = $_POST['brq_service_ideal_consumerCity'];
        }
    }
}