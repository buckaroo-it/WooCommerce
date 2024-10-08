<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Response\Response;

class CreditCardResponse extends Response
{
    public $cardNumberEnding = '';

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
        if (isset($_POST['brq_service_' . $this->payment_method . '_CardNumberEnding'])) {
            $this->cardNumberEnding = $this->_setPostVariable('brq_service_' . $this->payment_method . '_CardNumberEnding');
        }
    }
}