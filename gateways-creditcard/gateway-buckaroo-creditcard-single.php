<?php
/**
 * Parent creditcard class
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */
class Buckaroo_Creditcard_Single extends WC_Gateway_Buckaroo_Creditcard {

	public function __construct() {
		parent::__construct();
	}
	/** @inheritDoc */
	public function setCreditcardIcon() {
		$name = str_replace( 'buckaroo_creditcard_', '', $this->id );
		if ( $name === 'cartebleuevisa' ) {
			$name = 'cartebleue';
		}

		$icon = "creditcards/{$name}.svg";
		$this->setIcon( $icon, $icon );
	}
	/**
	 * Payment form on checkout page
	 *
	 * @return void
	 */
	public function payment_fields() {
		$this->renderTemplate( 'buckaroo_creditcard' );
	}
	/**
	 * Add fields to the form_fields() array, specific to this page.
	 *
	 * @access public
	 */
	public function init_form_fields() {

		parent::init_form_fields();
		unset(
			$this->form_fields['AllowedProvider'],
			$this->form_fields[ self::SHOW_IN_CHECKOUT_FIELD ]
		);
	}
	/**@inheritDoc */
	public function update_option( $key, $value = '' ) {
		if ( $key === 'enabled' ) {
			$this->updateList( $value === 'yes' );
		}
		return parent::update_option( $key, $value );
	}
	/**@inheritDoc */
	public function after_admin_options_update() {
		$this->updateList(
			$this->get_option( 'enabled', 'no' ) === 'yes'
		);
	}
	/**
	 * Remove or add checkout creditcard payment to the list
	 *
	 * @param boolean $show
	 *
	 * @return void
	 */
	public function updateList( $show ) {
		$credit_settings = get_option( 'woocommerce_buckaroo_creditcard_settings', null );

		if (
			$credit_settings === null ||
			! isset( $credit_settings['show_in_checkout'] ) ||
			! is_array( $credit_settings['show_in_checkout'] )
			) {
			return false;
		}

		$list             = $credit_settings['show_in_checkout'];
		$creditcardMethod = str_replace( 'buckaroo_creditcard_', '', $this->id );
		if ( in_array( $creditcardMethod, $list ) ) {
			$list = array_diff( $list, array( $creditcardMethod ) );
		}
		if ( $show ) {
			$list[] = $creditcardMethod;
		}

		$credit_settings['show_in_checkout'] = $list;

		return update_option( 'woocommerce_buckaroo_creditcard_settings', $credit_settings );
	}
}
