<?php

namespace Buckaroo\Woocommerce\Gateways\Ideal;

use Buckaroo\Woocommerce\Response\Response;

class IdealResponse extends Response
{
    public $consumerIssuer;
    public $consumerName;
    public $consumerAccountNumber;
    public $consumerCity;
    public $order;
    public $transactionId;

    /**
     * @access protected
     */
    protected function _parseSoapResponseChild()
    {
    }

    /**
     * @access protected
     */
    protected function _parsePostResponseChild()
    {
        if (isset($_POST['brq_service_ideal_consumerIssuer'])) {
            $this->consumerIssuer = $this->_setPostVariable('brq_service_ideal_consumerIssuer');
        }
        if (isset($_POST['brq_service_ideal_consumerName'])) {
            $this->consumerName = $this->_setPostVariable('brq_service_ideal_consumerName');
        }
        if (isset($_POST['brq_service_ideal_consumerAccountNumber'])) {
            $this->consumerAccountNumber = $this->_setPostVariable('brq_service_ideal_consumerAccountNumber');
        }
        if (isset($_POST['brq_service_ideal_consumerCity'])) {
            $this->consumerCity = $this->_setPostVariable('brq_service_ideal_consumerCity');
        }
    }
}