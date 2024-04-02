<?php

namespace WC_Buckaroo\Dependencies\Psr\Http\Client;

use WC_Buckaroo\Dependencies\Psr\Http\Message\RequestInterface;
use WC_Buckaroo\Dependencies\Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \WC_Buckaroo\Dependencies\Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface;
}
