<?php

require_once __DIR__ . '/buckaroopaypal/paypalresponse.php';
require_once __DIR__ . '/ideal/idealresponse.php';
require_once __DIR__ . '/transfer/transferresponse.php';
require_once __DIR__ . '/creditcard/creditcardresponse.php';
require_once __DIR__ . '/giftcard/giftcardresponse.php';
require_once __DIR__ . '/responsedefault.php';

/**
 * @package Buckaroo
 */
class BuckarooResponseFactory {

	private static function getPaymentMethod( $data = null ) {

		$paymentMethod = 'default';

		// 1) SOAP response
		if ( ! is_null( $data ) && ( $data[0] != false ) ) {
			if ( isset( $data[0]->ServiceCode ) ) {
				$paymentMethod = $data[0]->ServiceCode;
			}
		}//2) HTTP ???
		elseif ( isset( $_POST['brq_payment_method'] ) && is_string( $_POST['brq_payment_method'] ) ) { // brq_payment_method - The service code identifying the type of payment that has occurred.
			$paymentMethod = sanitize_text_field( $_POST['brq_payment_method'] );
		} // HTTP ???
		elseif ( isset( $_POST['brq_transaction_method'] ) && is_string( $_POST['brq_transaction_method'] ) ) { // brq_ transaction_method The service code identifying the type of transaction that has occurred. (If no payment has occurred, for example when a customer cancels on the redirect page.
			$paymentMethod = sanitize_text_field( $_POST['brq_transaction_method'] );
		}

		return $paymentMethod;
	}

	// If $data is not null - SOAP response, otherwise HTTP response
	final public static function getResponse( $data = null ) {

		$paymentmethod = self::getPaymentMethod( $data );

		switch ( $paymentmethod ) {
			case 'ideal':
				return new BuckarooIdealResponse( $data );
				break;
			case 'transfer':
				return new BuckarooTransferResponse( $data );
				break;
			case 'paypal':
				return new BuckarooPayPalResponse( $data );
				break;
			default:
				if ( stripos( BuckarooConfig::get( 'BUCKAROO_CREDITCARD_CARDS' ), $paymentmethod ) !== false ) {
					return new BuckarooCreditCardResponse( $data );
				} elseif ( stripos( BuckarooConfig::get( 'BUCKAROO_GIFTCARD_CARDS' ), $paymentmethod ) !== false ) {
					return new BuckarooGiftCardResponse( $data );
				} else {
					return new BuckarooResponseDefault( $data );
				}
				break;
		}
	}
}
