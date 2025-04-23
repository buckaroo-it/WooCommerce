<?php

namespace Buckaroo\Woocommerce\Constraints;

use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;

class BuckarooTransactionStatus {

	const STATUS_PENDING       = 'pending';
	const STATUS_PROCESSING    = 'processing';
	const STATUS_ON_HOLD       = 'on-hold';
	const STATUS_COMPLETED     = 'completed';
	const STATUS_CANCELLED     = 'cancelled';
	const STATUS_REFUNDED      = 'refunded';
	const STATUS_FAILED        = 'failed';
	const STATUS_REQUEST_ERROR = 'request-error';

	/**
	 * Map Buckaroo transaction statuses to WooCommerce order statuses.
	 *
	 * @param string|int $status Buckaroo transaction status code.
	 * @return string WooCommerce order status.
	 */
	public static function fromTransactionStatus( $status ): string {
		switch ( $status ) {
			case ResponseStatus::BUCKAROO_STATUSCODE_SUCCESS:
				return self::STATUS_COMPLETED;
			case ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_USER_INPUT:
			case ResponseStatus::BUCKAROO_STATUSCODE_PENDING_PROCESSING:
			case ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER:
			case ResponseStatus::BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD:
				return self::STATUS_ON_HOLD;
			case ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_USER:
			case ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT:
				return self::STATUS_CANCELLED;
			default:
				return self::STATUS_FAILED;
		}
	}

	public static function getMessageFromCode( $code ): string {
		switch ( $code ) {
			case ResponseStatus::BUCKAROO_STATUSCODE_SUCCESS:
				return 'Success';
			case ResponseStatus::BUCKAROO_STATUSCODE_FAILED:
				return 'Payment failure';
			case ResponseStatus::BUCKAROO_STATUSCODE_VALIDATION_FAILURE:
				return 'Validation error';
			case ResponseStatus::BUCKAROO_STATUSCODE_TECHNICAL_ERROR:
				return 'Technical error';
			case ResponseStatus::BUCKAROO_STATUSCODE_REJECTED:
				return 'Payment rejected';
			case ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_USER_INPUT:
				return 'Waiting for user input';
			case ResponseStatus::BUCKAROO_STATUSCODE_PENDING_PROCESSING:
				return 'Waiting for processor';
			case ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER:
				return 'Waiting on consumer action';
			case ResponseStatus::BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD:
				return 'Payment on hold';
			case ResponseStatus::BUCKAROO_STATUSCODE_PENDING_APPROVAL:
				return 'Pending for approval';
			case ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_USER:
				return 'Cancelled by consumer';
			case ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT:
				return 'Cancelled by merchant';
			default:
				return 'Unknown status';
		}
	}
}
