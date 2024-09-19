<?php

/**
 * Core class for loading gateways
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

class Buckaroo_Load_Gateways {

	protected $dir;

	protected $methods = array(
		// comment payment methods you do not want to use
		'AfterPay'                => array(
			'filename'  => 'gateway-buckaroo-afterpay.php',
			'classname' => 'WC_Gateway_Buckaroo_AfterPay',
		),
		'AfterPayNew'             => array(
			'filename'  => 'gateway-buckaroo-afterpaynew.php',
			'classname' => 'WC_Gateway_Buckaroo_AfterPaynew',
		),
		'Applepay'                => array(
			'filename'  => 'gateway-buckaroo-applepay.php',
			'classname' => 'WC_Gateway_Buckaroo_Applepay',
		),
		'Bancontact / MisterCash' => array(
			'filename'  => 'gateway-buckaroo-mistercash.php',
			'classname' => 'WC_Gateway_Buckaroo_MisterCash',
		),
		'Bank Transfer'           => array(
			'filename'  => 'gateway-buckaroo-transfer.php',
			'classname' => 'WC_Gateway_Buckaroo_Transfer',
		),
        'Blik'                    => array(
            'filename'  => 'gateway-buckaroo-blik.php',
            'classname' => 'WC_Gateway_Buckaroo_Blik',
        ),
		'Belfius'                 => array(
			'filename'  => 'gateway-buckaroo-belfius.php',
			'classname' => 'WC_Gateway_Buckaroo_Belfius',
		),
		'Billink'                 => array(
			'filename'  => 'gateway-buckaroo-billink.php',
			'classname' => 'WC_Gateway_Buckaroo_Billink',
		),
		'Creditcards'             => array(
			'filename'  => 'gateway-buckaroo-creditcard.php',
			'classname' => 'WC_Gateway_Buckaroo_Creditcard',
		),
		'EPS'                     => array(
			'filename'  => 'gateway-buckaroo-eps.php',
			'classname' => 'WC_Gateway_Buckaroo_EPS',
		),
		'Giftcards'               => array(
			'filename'  =>
			'gateway-buckaroo-giftcard.php',
			'classname' => 'WC_Gateway_Buckaroo_Giftcard',
		),
		'iDeal'                   => array(
			'filename'  =>
			'gateway-buckaroo-ideal.php',
			'classname' => 'WC_Gateway_Buckaroo_Ideal',
		),
		'In3'                     => array(
			'filename'  => 'gateway-buckaroo-in3.php',
			'classname' => 'WC_Gateway_Buckaroo_In3',
		),
		'KBC'                     => array(
			'filename'  => 'gateway-buckaroo-kbc.php',
			'classname' => 'WC_Gateway_Buckaroo_KBC',
		),
		'KlarnaPay'               => array(
			'filename'  => 'gateway-buckaroo-klarnapay.php',
			'classname' => 'WC_Gateway_Buckaroo_KlarnaPay',
		),
		'KlarnaPII'               => array(
			'filename'  => 'gateway-buckaroo-klarnapii.php',
			'classname' => 'WC_Gateway_Buckaroo_KlarnaPII',
		),
		'KlarnaKp'                => array(
			'filename'  => 'gateway-buckaroo-klarnakp.php',
			'classname' => 'WC_Gateway_Buckaroo_KlarnaKp',
		),
		'KnakenSettle'            => array(
			'filename'  => 'gateway-buckaroo-knakensettle.php',
			'classname' => 'WC_Gateway_Buckaroo_KnakenSettle',
		),
		'P24'                     => array(
			'filename'  =>
			'gateway-buckaroo-p24.php',
			'classname' => 'WC_Gateway_Buckaroo_P24',
		),
		'Payconiq'                => array(
			'filename'  => 'gateway-buckaroo-payconiq.php',
			'classname' => 'WC_Gateway_Buckaroo_Payconiq',
		),
		'PayPal'                  => array(
			'filename'  => 'gateway-buckaroo-paypal.php',
			'classname' => 'WC_Gateway_Buckaroo_Paypal',
		),
		'PayPerEmail'             => array(
			'filename'  => 'gateway-buckaroo-payperemail.php',
			'classname' => 'WC_Gateway_Buckaroo_PayPerEmail',
		),
		'SepaDirectDebit'         => array(
			'filename'  => 'gateway-buckaroo-sepadirectdebit.php',
			'classname' => 'WC_Gateway_Buckaroo_SepaDirectDebit',
		),
		'Sofortbanking'           => array(
			'filename'  => 'gateway-buckaroo-sofort.php',
			'classname' => 'WC_Gateway_Buckaroo_Sofortbanking',
		),
		'PayByBank'               => array(
			'filename'  => 'gateway-buckaroo-paybybank.php',
			'classname' => 'WC_Gateway_Buckaroo_PayByBank',
		),
		'Multibanco'              => array(
			'filename'  => 'gateway-buckaroo-multibanco.php',
			'classname' => 'WC_Gateway_Buckaroo_Multibanco',
		),
		'MBWay'                   => array(
			'filename'  => 'gateway-buckaroo-mbway.php',
			'classname' => 'WC_Gateway_Buckaroo_MBWay',
		),
	);
	public function __construct( $dir = null ) {
		if ( is_null( $dir ) ) {
			$dir = plugin_dir_path( BK_PLUGIN_FILE );
		}
		$this->dir = $dir;
	}
	/**
	 * Load necesary
	 *
	 * @return void
	 */
	public function load() {
		$this->add_exodus();
		$this->load_before();
		$this->load_gateways();
		$this->load_after();
		$this->enable_creditcards_in_checkout();
	}
	/**
	 * Enable credicard method when set to be shown individually in checkout page
	 *
	 * @return void
	 */
	public function enable_creditcards_in_checkout() {
		if ( ! get_transient( 'buckaroo_credicard_updated' ) ) {
			return;
		}

		$gatewayNames = $this->get_creditcards_to_show();

		if ( ! is_array( $gatewayNames ) ) {
			return;
		}

		foreach ( $gatewayNames as $name ) {
			$class = 'WC_Gateway_Buckaroo_' . ucfirst( $name );
			if ( class_exists( $class ) ) {
				var_dump( class_exists( $class ) );
				( new $class() )->update_option( 'enabled', 'yes' );
			}
		}
		delete_transient( 'buckaroo_credicard_updated' );
	}
	/**
	 * Hook function for `woocommerce_payment_gateways` hook
	 *
	 * @param array $methods
	 *
	 * @return array
	 */
	public function hook_gateways_to_woocommerce( $methods ) {
		foreach ( $this->sort_gateways_alfa( $this->get_all_gateways() ) as $method ) {
			$methods[] = $method['classname'];
		}
		return $methods;
	}
	/**
	 * load all gateways
	 *
	 * @return void
	 */
	protected function load_gateways() {
		foreach ( $this->methods as $method ) {
			$file = $this->dir . $method['filename'];
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
		require_once $this->dir . 'gateways-creditcard/gateway-buckaroo-creditcard-single.php';

		foreach ( $this->get_creditcard_methods() as $method ) {
			$file = $this->dir . $method['filename'];
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}
	/**
	 * Get all gateways
	 *
	 * @return array
	 */
	protected function get_all_gateways() {
		return array_merge(
			$this->methods,
			$this->get_creditcard_methods()
		);
	}
	/**
	 * Load before the gateways
	 *
	 * @return void
	 */
	protected function load_before() {
	}
	/**
	 * Load after the gateways
	 *
	 * @return void
	 */
	protected function load_after() {
		require_once $this->dir . 'push-buckaroo.php';
	}
	/**
	 * Get credicard payment methods
	 *
	 * @return array
	 */
	protected function get_creditcard_methods() {
		$creditcardMethods = array();

		foreach ( $this->get_creditcards_to_show() as $creditcard ) {
			if ( strlen( trim( $creditcard ) ) !== 0 ) {
				$creditcardMethods[ $creditcard . '_creditcard' ] = array(
					'filename'  => "gateways-creditcard/gateway-buckaroo-${creditcard}.php",
					'classname' => 'WC_Gateway_Buckaroo_' . ucfirst( $creditcard ),
				);
			}
		}
		return $creditcardMethods;
	}
	/**
	 * Get creditcards to show in checkout page
	 *
	 * @return array
	 */
	public function get_creditcards_to_show() {
		$credit_settings = get_option( 'woocommerce_buckaroo_creditcard_settings', null );

		if (
			$credit_settings !== null &&
			isset( $credit_settings['creditcardmethod'] ) &&
			$credit_settings['creditcardmethod'] === 'encrypt' &&
			isset( $credit_settings['show_in_checkout'] ) &&
			is_array( $credit_settings['show_in_checkout'] )
			) {
			return $credit_settings['show_in_checkout'];
		}
		return array();
	}
	/**
	 * Sort payment gateway alphabetically by name
	 *
	 * @param array $gateway
	 *
	 * @return array
	 */
	protected function sort_gateways_alfa( $gateways ) {
		uksort(
			$gateways,
			function ( $a, $b ) {
				return strcmp(
					strtolower( $a ),
					strtoLower( $b )
				);
			}
		);
		return $gateways;
	}
	/**
	 * Load exodus class
	 *
	 * @return void
	 */
	private function add_exodus() {
		if ( file_exists( dirname( BK_PLUGIN_FILE ) . '/buckaroo-exodus.php' ) && ! get_option( 'woocommerce_buckaroo_exodus' ) ) {
			$this->methods['Exodus Script'] = array(
				'filename'  => 'buckaroo-exodus.php',
				'classname' => 'WC_Gateway_Buckaroo_Exodus',
			);
		}
	}
}
