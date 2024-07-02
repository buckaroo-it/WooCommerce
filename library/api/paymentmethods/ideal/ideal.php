<?php

require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooIDeal extends BuckarooPaymentMethod {
	public $issuer;
	protected $data;
	public $channel;

	/**
	 * @access public
	 */
	public function __construct() {
		$this->type    = 'ideal';
		$this->version = 2;
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::Pay();
	 */
	public function Pay( $customVars = array() ) {

		if ( is_string( $this->issuer ) ) {
			$this->setCustomVar(
				'issuer',
				$this->issuer
			);
		} else {
			$this->setCustomVarWithoutType( array( 'continueOnIncomplete' => 'RedirectToHTML' ) );
		}
		return parent::Pay();
	}

	/**
	 * @access public
	 * @return array $issuerArray
	 */
	public static function getIssuerList() {
		$issuerArray = array(
			'ABNANL2A' => array(
				'name' => 'ABN AMRO',
				'logo' => 'abnamro.svg',
			),
			'ASNBNL21' => array(
				'name' => 'ASN Bank',
				'logo' => 'asnbank.svg',
			),
			'INGBNL2A' => array(
				'name' => 'ING',
				'logo' => 'ing.svg',
			),
			'RABONL2U' => array(
				'name' => 'Rabobank',
				'logo' => 'rabobank.svg',
			),
			'SNSBNL2A' => array(
				'name' => 'SNS Bank',
				'logo' => 'sns.svg',
			),
			'RBRBNL21' => array(
				'name' => 'RegioBank',
				'logo' => 'regiobank.svg',
			),
			'TRIONL2U' => array(
				'name' => 'Triodos Bank',
				'logo' => 'triodos.svg',
			),
			'FVLBNL22' => array(
				'name' => 'Van Lanschot Kempen',
				'logo' => 'vanlanschot.svg',
			),
			'KNABNL2H' => array(
				'name' => 'Knab',
				'logo' => 'knab.svg',
			),
			'BUNQNL2A' => array(
				'name' => 'bunq',
				'logo' => 'bunq.svg',
			),
			'REVOLT21' => array(
				'name' => 'Revolut',
				'logo' => 'revolut.svg',
			),
			'BITSNL2A' => array(
				'name' => 'Yoursafe',
				'logo' => 'yoursafe.svg',
			),
			'NTSBDEB1' => array(
				'name' => 'N26',
				'logo' => 'n26.svg',
			),
			'NNBANL2G' => array(
				'name' => 'Nationale Nederlanden',
				'logo' => 'nn.svg',
			),
		);
		return $issuerArray;
	}
}
