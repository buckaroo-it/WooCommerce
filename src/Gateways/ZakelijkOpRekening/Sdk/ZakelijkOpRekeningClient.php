<?php

namespace Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\Sdk;

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Logger;
use BuckarooDeps\Buckaroo\Adapter\Bpe\BpeResult;
use BuckarooDeps\Buckaroo\Adapter\Contracts\Result;
use BuckarooDeps\Buckaroo\Services\PayloadService;

/**
 * Sends In3 Capture through ZakelijkOpRekeningPaymentMethod.
 * Default BuckarooClient::process() uses the processor action, which is authorize.
 */
class ZakelijkOpRekeningClient extends BuckarooClient
{
    public function process(AbstractProcessor $processor, array $additionalData = []): Result
    {
        $serviceCode = $processor->gateway->getServiceCode($processor);
        $requestData = array_merge($processor->getBody(), $additionalData);

        Logger::log(__METHOD__ . '|1|', [get_class($processor), $serviceCode, 'capture', $requestData]);

        $method = new ZakelijkOpRekeningPaymentMethod($this->client(), $serviceCode);
        $method->setPayload((new PayloadService($requestData))->toArray());

        return new BpeResult($method->capture());
    }
}
