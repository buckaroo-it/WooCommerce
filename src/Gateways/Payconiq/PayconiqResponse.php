<?php

namespace Buckaroo\Woocommerce\Gateways\Payconiq;

use Buckaroo\Woocommerce\Response\Response;

class PayconiqResponse extends Response
{
    public $paylink = '';

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