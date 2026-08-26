<?php

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;

class InMemoryBuckarooClient extends BuckarooClient
{
    /** @var TransactionResponse */
    private $response;

    /** @var TransactionResponse|null */
    private $statusResponse;

    /** @var Throwable|null */
    public $statusException;

    /** @var string[] */
    public $statusRequests = [];

    /** @var string|null */
    public $service;

    /** @var string|null */
    public $action;

    /** @var array<string, mixed> */
    public $payload = [];

    /** @var int */
    public $sendCount = 0;

    public function __construct(TransactionResponse $response, ?TransactionResponse $statusResponse = null)
    {
        $this->response = $response;
        $this->statusResponse = $statusResponse;
    }

    public function transactionStatus(string $transactionKey): TransactionResponse
    {
        $this->statusRequests[] = $transactionKey;
        if ($this->statusException instanceof Throwable) {
            throw $this->statusException;
        }
        if ($this->statusResponse === null) {
            throw new RuntimeException('No transaction status response configured.');
        }

        return $this->statusResponse;
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
