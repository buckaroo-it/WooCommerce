<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooEmpayment extends BuckarooPaymentMethod {

	/**
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->type    = 'empayment';
		$this->version = 1;
	}

	/**
	 * @access public
	 * @return void
	 */
	public function Pay() {
		return null;
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return callable parent::Pay()
	 */
	public function EmPay( $customVars ) {
		$this->data['customVars'][ $this->type ]['reference']                = $this->invoiceId;
		$this->data['customVars'][ $this->type ]['emailAddress']             = $customVars['emailAddress'];
		$this->data['customVars'][ $this->type ]['FirstName']['value']       = $customVars['FirstName'];
		$this->data['customVars'][ $this->type ]['FirstName']['group']       = 'person';
		$this->data['customVars'][ $this->type ]['LastName']['value']        = $customVars['LastName'];
		$this->data['customVars'][ $this->type ]['LastName']['group']        = 'person';
		$this->data['customVars'][ $this->type ]['Initials']['value']        = $customVars['Initials'];
		$this->data['customVars'][ $this->type ]['Initials']['group']        = 'person';
		$this->data['customVars'][ $this->type ]['browserAgent']['value']    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$this->data['customVars'][ $this->type ]['browserAgent']['group']    = 'clientInfo';
		$this->data['customVars'][ $this->type ]['Type']['value']            = 'DOM';
		$this->data['customVars'][ $this->type ]['Type']['group']            = 'bankaccount';
		$this->data['customVars'][ $this->type ]['DomesticCountry']['value'] = '528';
		$this->data['customVars'][ $this->type ]['DomesticCountry']['group'] = 'bankaccount';
		$this->data['customVars'][ $this->type ]['Collect']['value']         = '1';
		$this->data['customVars'][ $this->type ]['Collect']['group']         = 'bankaccount';

		foreach ( $customVars['ADDRESS'] as $key => $adress ) {
			foreach ( $adress as $key2 => $value ) {
				$this->data['customVars'][ $this->type ][ $key2 ][ $key ]['value'] = $value;
				$this->data['customVars'][ $this->type ][ $key2 ][ $key ]['group'] = 'address';
			}
		}
		return parent::Pay();
	}
}
