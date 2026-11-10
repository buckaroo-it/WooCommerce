<?php

use BuckarooDeps\Buckaroo\Transaction\Client;
use BuckarooDeps\Buckaroo\Transaction\Request\HttpClient\HttpClientInterface;

/**
 * Records what the BPE SDK sends and answers with a fixed JSON body.
 * The BPE SDK has no public way to replace its HTTP client, so attachTo() sets it through reflection.
 */
class RecordingBpeHttpClient implements HttpClientInterface
{
    /** @var list<array{url: string, headers: array, method: string, body: ?string}> */
    public $requests = [];

    /** @var array<string, mixed> */
    private $response;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    /** @param Client $transactionClient the BPE client's transaction client, from client() */
    public static function attachTo(Client $transactionClient, array $response = ['Key' => 'BPE-KEY', 'Status' => ['Code' => ['Code' => 190]]]): self
    {
        $fake = new self($response);
        $property = new ReflectionProperty(Client::class, 'httpClient');
        $property->setValue($transactionClient, $fake);

        return $fake;
    }

    public function call(string $url, array $headers, string $method, ?string $data = null)
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'method' => $method, 'body' => $data];

        return [null, $this->response];
    }

    /** The HMAC header holds a random nonce and the time, so it differs on every request. */
    public function requestsWithoutSignature(): array
    {
        return array_map(function (array $request) {
            $request['headers'] = array_values(array_filter($request['headers'], function ($header) {
                return ! (is_string($header) && strpos($header, 'Authorization: hmac ') === 0);
            }));

            return $request;
        }, $this->requests);
    }
}
