<?php

namespace WC_Buckaroo\Dependencies\Buckaroo\Transaction\Request\HttpClient;

use WC_Buckaroo\Dependencies\Buckaroo\Exceptions\BuckarooException;
use WC_Buckaroo\Dependencies\Buckaroo\Exceptions\TransferException;
use WC_Buckaroo\Dependencies\Buckaroo\Handlers\Logging\Subject;
use WC_Buckaroo\Dependencies\GuzzleHttp\Client;
use WC_Buckaroo\Dependencies\GuzzleHttp\ClientInterface;
use WC_Buckaroo\Dependencies\GuzzleHttp\Exception\GuzzleException;
use WC_Buckaroo\Dependencies\GuzzleHttp\Exception\RequestException;

class GuzzleHttpClientV5 extends HttpClientAbstract
{
    /**
     * @var Subject
     */
    protected Subject $logger;
    protected ClientInterface $httpClient;

    public function __construct(Subject $logger)
    {
        parent::__construct($logger);
        $this->logger = $logger;

        $this->httpClient = new Client([
            'timeout' => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
        ]);
    }

    /**
     * @param string $url
     * @param array $headers
     * @param string $method
     * @param string|null $data
     * @return array|mixed
     * @throws TransferException
     * @throws BuckarooException|GuzzleException
     */

    public function call(string $url, array $headers, string $method, string $data = null)
    {
        $headers = $this->convertHeadersFormat($headers);

        $request = $this->httpClient->createRequest($method, $url, [
            'headers' => $headers,
            'body' => $data,
        ]);

        try
        {
            $response = $this->httpClient->send($request);

            $result = (string) $response->getBody();

            $this->logger->info('RESPONSE HEADERS: ' . json_encode($response->getHeaders()));
            $this->logger->info('RESPONSE BODY: ' . $response->getBody());
        } catch (RequestException $e) {
            throw new TransferException($this->logger, "Transfer failed", 0, $e);
        }

        $result = $this->getDecodedResult($response, $result);

        return [
            $response,
            $result,
        ];
    }
}
