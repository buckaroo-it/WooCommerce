<?php
require_once __DIR__ . '/../include.php';

/**
 * @package Buckaroo
 */
abstract class BuckarooAbstract {

	const CODE_SUCCESS               = 190;
	const CODE_FAILED                = 490;
	const CODE_VALIDATION_FAILURE    = 491;
	const CODE_TECHNICAL_FAILURE     = 492;
	const CODE_REJECTED              = 690;
	const CODE_PENDING_INPUT         = 790;
	const CODE_PENDING_PROCESSING    = 791;
	const CODE_AWAITING_CONSUMER     = 792;
	const CODE_ON_HOLD               = 793;
	const CODE_CANCELLED_BY_USER     = 890;
	const CODE_CANCELLED_BY_MERCHANT = 891;


	const STATUS_COMPLETED     = 'completed';
	const STATUS_ON_HOLD       = 'on-hold';
	const STATUS_CANCELED      = 'cancelled';
	const STATUS_FAILED        = 'failed';
	const STATUS_REQUEST_ERROR = 'request-error';

	/**
	 *  List of possible response codes sent by buckaroo.
	 *  This is the list for the BPE 3.0 gateway.
	 */
	public $responseCodes = array(
		self::CODE_SUCCESS               => array(
			'message' => 'Success',
			'status'  => self::STATUS_COMPLETED,
		),
		self::CODE_FAILED                => array(
			'message' => 'Payment failure',
			'status'  => self::STATUS_FAILED,
		),
		self::CODE_VALIDATION_FAILURE    => array(
			'message' => 'Validation error',
			'status'  => self::STATUS_FAILED,
		),
		self::CODE_TECHNICAL_FAILURE     => array(
			'message' => 'Technical error',
			'status'  => self::STATUS_FAILED,
		),
		self::CODE_REJECTED              => array(
			'message' => 'Payment rejected',
			'status'  => self::STATUS_FAILED,
		),
		self::CODE_PENDING_INPUT         => array(
			'message' => 'Waiting for user input',
			'status'  => self::STATUS_ON_HOLD,
		),
		self::CODE_PENDING_PROCESSING    => array(
			'message' => 'Waiting for processor',
			'status'  => self::STATUS_ON_HOLD,
		),
		self::CODE_AWAITING_CONSUMER     => array(
			'message' => 'Waiting on consumer action',
			'status'  => self::STATUS_ON_HOLD,
		),
		self::CODE_ON_HOLD               => array(
			'message' => 'Payment on hold',
			'status'  => self::STATUS_ON_HOLD,
		),
		self::CODE_CANCELLED_BY_USER     => array(
			'message' => 'Cancelled by consumer',
			'status'  => self::STATUS_CANCELED,
		),
		self::CODE_CANCELLED_BY_MERCHANT => array(
			'message' => 'Cancelled by merchant',
			'status'  => self::STATUS_FAILED,
		),
	);

	/**
	 * Custom array sort function.
	 *
	 * @param array $array
	 * @param array $sortedArray
	 */
	public function buckarooSort( $array ) {
		$arrayToSort = array();
		$origArray   = array();
		foreach ( $array as $key => $value ) {
			$arrayToSort[ strtolower( $key ) ] = $value;
			$origArray[ strtolower( $key ) ]   = $key;
		}

		ksort( $arrayToSort );

		$sortedArray = array();
		foreach ( $arrayToSort as $key => $value ) {
			$key                 = $origArray[ $key ];
			$sortedArray[ $key ] = $value;
		}

		return $sortedArray;
	}
}

/**
 * @package Buckaroo
 */
class Software {
	public $PlatformName;
	public $PlatformVersion;
	public $ModuleSupplier;
	public $ModuleName;
	public $ModuleVersion;
}
