<?php

namespace Buckaroo\Woocommerce\ResponseParser;

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\Transfer\TransferResponse;

class ResponseRegistry {

	final public static function getResponse( array $data = array() ): ResponseParser {
		$responseParser = ResponseParser::make( $data );

		if ( $responseParser->getStatusCode() ) {
			$responseParser->set( 'status', BuckarooTransactionStatus::fromTransactionStatus( $responseParser->getStatusCode() ) );
		}

		switch ( $responseParser->getPaymentMethod() ) {
			case 'transfer':
				return ( new TransferResponse( $responseParser ) )->toResponse();
			default:
				return $responseParser;
		}
	}
}
