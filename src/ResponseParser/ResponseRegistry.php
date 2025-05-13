<?php

namespace Buckaroo\Woocommerce\ResponseParser;

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\Transfer\TransferResponse;

class ResponseRegistry
{
    final public static function getResponse(array $data = []): ResponseParser
    {
        $responseParser = ResponseParser::make($data);

        if ($responseParser->getStatusCode()) {
            $responseParser->set('coreStatus', BuckarooTransactionStatus::fromTransactionStatus($responseParser->getStatusCode()));
        }

        switch ($responseParser->getPaymentMethod()) {
            case 'transfer':
                return (new TransferResponse($responseParser))->toResponse();
            default:
                return $responseParser;
        }
    }

    final public static function getResponseFromRequest(): ResponseParser
    {
        if (
            isset($_SERVER['REQUEST_METHOD']) &&
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
        ) {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
        } else {
            $data = $_GET;
        }

        return self::getResponse($data);
    }
}
