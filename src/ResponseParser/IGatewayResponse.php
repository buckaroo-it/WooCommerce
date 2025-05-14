<?php

namespace Buckaroo\Woocommerce\ResponseParser;

interface IGatewayResponse
{
    public function toResponse(): ResponseParser;
}
