<?php

namespace Buckaroo\Woocommerce\ResponseParser;

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\Transfer\TransferResponse;

class ResponseRegistry
{
    final public static function getResponse(array $data = []): ResponseParser
    {
        $responseParser = ResponseParser::make($data);
        $responseParser->set('status', BuckarooTransactionStatus::fromTransactionStatus($responseParser->getStatusCode()));

        switch ($responseParser->getPaymentMethod()) {
            case 'transfer':
                return (new TransferResponse($responseParser))->toResponse();
            default:
                return $responseParser;
        }
    }
}