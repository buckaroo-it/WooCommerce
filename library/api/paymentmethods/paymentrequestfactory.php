<?php

require_once __DIR__ . '/functions.php';


/**
 * @package Buckaroo
 */
class BuckarooPaymentRequestFactory {

	const REQUEST_TYPE_PAYPAL          = 'buckaroopaypal';
	const REQUEST_TYPE_EMPAYMENT       = 'empayment';
	const REQUEST_TYPE_IDEAL           = 'ideal';
	const REQUEST_TYPE_PAYCONIQ        = 'payconiq';
	const REQUEST_TYPE_DIRECTDEBIT     = 'directdebit';
	const REQUEST_TYPE_SEPADIRECTDEBIT = 'sepadirectdebit';
	const REQUEST_TYPE_MISTERCASH      = 'bancontactmrcash';
	const REQUEST_TYPE_EMAESTRO        = 'maestro';
	const REQUEST_TYPE_SOFORTBANKING   = 'sofortueberweisung';
	const REQUEST_TYPE_BELFIUS         = 'belfius';
	const REQUEST_TYPE_BLIK            = 'blik';
	const REQUEST_TYPE_GIFTCARD        = 'giftcard';
	const REQUEST_TYPE_CREDITCARD      = 'creditcard';
	const REQUEST_TYPE_TRANSFER        = 'transfer';

	public static $valid_request_types = array(
		self::REQUEST_TYPE_PAYPAL          => 'BuckarooPayPal',
		self::REQUEST_TYPE_EMPAYMENT       => 'Empayment',
		self::REQUEST_TYPE_IDEAL           => 'IDeal',
		self::REQUEST_TYPE_DIRECTDEBIT     => 'DirectDebit',
		self::REQUEST_TYPE_SEPADIRECTDEBIT => 'SepaDirectDebit',
		self::REQUEST_TYPE_PAYCONIQ        => 'Payconiq',
		self::REQUEST_TYPE_MISTERCASH      => 'MisterCash',
		self::REQUEST_TYPE_EMAESTRO        => 'EMaestro',
		self::REQUEST_TYPE_SOFORTBANKING   => 'Sofortbanking',
		self::REQUEST_TYPE_BELFIUS         => 'Belfius',
		self::REQUEST_TYPE_BLIK            => 'Blik',
		self::REQUEST_TYPE_GIFTCARD        => 'GiftCard',
		self::REQUEST_TYPE_CREDITCARD      => 'CreditCard',
		self::REQUEST_TYPE_TRANSFER        => 'Transfer',
	);

	final public static function Create( $request_type_id, $data = array() ) {

		$class_name = self::$valid_request_types[ $request_type_id ];
		buckaroo_autoload( $class_name );
		if ( ! class_exists( $class_name ) ) {
			throw new Exception( 'Payment method not found', '1' ); // TODO: ExceptionPayment
		}
		return new $class_name( $data );
	}
}
