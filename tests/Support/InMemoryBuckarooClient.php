<?php

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;

class InMemoryBuckarooClient extends BuckarooClient
{
    /** @var TransactionResponse */
    private $response;

    /** @var string|null */
    public $service;

    /** @var string|null */
    public $action;

    /** @var array<string, mixed> */
    public $payload = [];

    /** @var int */
    public $sendCount = 0;

    public function __construct(TransactionResponse $response)
    {
        $this->response = $response;
    }

    public function process(AbstractProcessor $processor, array $additionalData = []): TransactionResponse
    {
        $this->sendCount++;
        $this->service = $processor->gateway->getServiceCode($processor);
        $this->action = $processor->getAction();
        $this->payload = array_merge($processor->getBody(), $additionalData);

        return $this->response;
    }
}
