<?php

namespace Buckaroo\Woocommerce\Response;

class ResponseDefault extends Response
{
    public $transactionId;
    public $order;

    protected function _parseSoapResponseChild()
    {
    }

    protected function _parsePostResponseChild()
    {
    }
}