<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;

/**
 * Records each request instead of sending it, and answers with the queued
 * responses in order. A queued Throwable is thrown, as a transport error.
 */
class RecordingBuckarooClient extends BuckarooClient
{
    /** @var array<int, TransactionResponse|Throwable> */
    private $responses;

    /** @var int */
    public $sendCount = 0;

    /** @var array<int, array<string, mixed>> Request data added by the caller */
    public $requests = [];

    /** @var callable|null Runs while the request is "on the wire" */
    public $onSend;

    public function __construct(...$responses)
    {
        $this->responses = $responses;
    }

    public function process(AbstractProcessor $processor, array $additionalData = []): TransactionResponse
    {
        $this->sendCount++;
        $this->requests[] = $additionalData;

        if (is_callable($this->onSend)) {
            ($this->onSend)();
        }

        $response = array_shift($this->responses);
        if ($response instanceof Throwable) {
            throw $response;
        }
        if (! $response instanceof TransactionResponse) {
            throw new RuntimeException('No response queued.');
        }

        return $response;
    }

    public static function response(int $statusCode, string $transactionKey, ?string $amountCredit = null): TransactionResponse
    {
        $data = [
            'Key' => $transactionKey,
            'Status' => ['Code' => ['Code' => $statusCode], 'SubCode' => ['Code' => '', 'Description' => '']],
        ];
        if ($amountCredit !== null) {
            $data['AmountCredit'] = $amountCredit;
        }
        if ($statusCode !== 190) {
            $data['Message'] = 'Declined';
        }

        return new TransactionResponse(null, $data);
    }
}
