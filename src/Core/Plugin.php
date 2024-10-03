<?php

namespace Buckaroo\Woocommerce\Core;

class Plugin
{
    public function registerGateways(): PaymentGatewayRegistry
    {
        return (new PaymentGatewayRegistry)->load();
    }

}