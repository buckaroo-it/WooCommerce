<?php

namespace Buckaroo\Woocommerce\ResponseParser;

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;

class ResponseRegistry
{
    final public static function getResponse(array $data = []): ResponseParser
    {
        $responseParser = ResponseParser::make($data);

        if ($responseParser->getStatusCode()) {
            $responseParser->set('coreStatus', BuckarooTransactionStatus::fromTransactionStatus($responseParser->getStatusCode()));
        }

        return $responseParser;
    }

    final public static function getResponseFromRequest(): ResponseParser
    {
        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
            : '';
        $contentType = isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])
            ? sanitize_text_field(wp_unslash($_SERVER['CONTENT_TYPE']))
            : '';

        // The reply is kept as sent: its signature is verified over the original values.
        if ($method === 'POST' && strpos($contentType, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
        } elseif ($method === 'POST') {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Buckaroo reply, verified by its signature before use.
            $data = stripslashes_deep($_POST);
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Buckaroo reply, verified by its signature before use.
            $data = stripslashes_deep($_GET);
        }

        return self::getResponse($data);
    }
}
