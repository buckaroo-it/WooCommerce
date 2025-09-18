<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

class AfterpayNewRefundProcessor extends AbstractAfterpayRefundProcessor
{
    protected function getVatData(float $vatPercentage): array
    {
        return ['vatPercentage' => $vatPercentage];
    }
}
