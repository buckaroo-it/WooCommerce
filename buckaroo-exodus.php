<?php
	/**
	 * @package Buckaroo
	 */
class WC_Gateway_Buckaroo_Exodus extends WC_Gateway_Buckaroo {
	function __construct() {

		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		$this->title      = 'Exodus';
		$this->has_fields = false;

		$this->supports = array(
			'products',
			'refunds',
		);
	}

	/**
	 * Actual Migration Script
	 *
	 * @access public
	 */
	public function exodus_actions() {

		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		// Mistercash = Bancontact option
		$options_tocheck = array(
			'woocommerce_buckaroo_ideal_settings',
			'woocommerce_buckaroo_creditcard_settings',
			'woocommerce_buckaroo_payconiq_settings',
			'woocommerce_buckaroo_paypal_settings',
			'woocommerce_buckaroo_afterpay_settings',
			'woocommerce_buckaroo_afterpaynew_settings',
			'woocommerce_buckaroo_applepay_settings',
			'woocommerce_buckaroo_mistercash_settings',
			'woocommerce_buckaroo_transfer_settings',
			'woocommerce_buckaroo_emaestro_settings',
			'woocommerce_buckaroo_nexi_settings',
			'woocommerce_buckaroo_postepay_settings',
			'woocommerce_buckaroo_giftcard_settings',
			'woocommerce_buckaroo_sofortbanking_settings',
			'woocommerce_buckaroo_blik_settings',
			'woocommerce_buckaroo_belfius_settings',
			'woocommerce_buckaroo_sepadirectdebit_settings',
			'woocommerce_buckaroo_payperemail_settings',
		);

		$n           = 0;
		$key_options = $certificate_contents = $certificate_name = '';
		while ( ! empty( $options_tocheck[ $n ] ) && $key_options == '' ) {
			$options = get_option( $options_tocheck[ $n ], null );

			// Check Certificate contents
			$certificate_name     = ! empty( $options['certificate'] ) ? $options['certificate'] : 'BuckarooPrivateKey.pem';
			$upload_dir           = wp_upload_dir();
			$certificate_contents = '';
			if ( file_exists( $upload_dir['basedir'] . '/woocommerce_uploads/' . $certificate_name ) ) {
				$certificate_contents = file_get_contents( $upload_dir['basedir'] . '/woocommerce_uploads/' . $certificate_name );
			}
			if ( $certificate_contents == '' ) {
				++$n;
				continue;
			}

			// Check all other gubbins
			if ( empty( $options['culture'] ) || $options['culture'] == '' || $options['culture'] == null ) {
				++$n;
				continue;
			}
			if ( empty( $options['merchantkey'] ) || $options['merchantkey'] == '' || $options['merchantkey'] == null ) {
				++$n;
				continue;
			}
			if ( empty( $options['secretkey'] ) || $options['secretkey'] == '' || $options['secretkey'] == null ) {
				++$n;
				continue;
			}
			if ( empty( $options['thumbprint'] ) || $options['thumbprint'] == '' || $options['thumbprint'] == null ) {
				++$n;
				continue;
			}
			if ( empty( $options['currency'] ) || $options['currency'] == '' || $options['currency'] == null ) {
				++$n;
				continue;
			}
			$key_options = $options_tocheck[ $n ];
		}

		if ( $key_options != '' ) {
			$keys      = get_option( $key_options, null );
			$timestamp = date( 'Y-m-d @ H:i:s', time() );

			$onetime_settings2                           = array();
			$onetime_settings2['merchantkey']            = $keys['merchantkey'];
			$onetime_settings2['secretkey']              = $keys['secretkey'];
			$onetime_settings2['thumbprint']             = $keys['thumbprint'];
			$onetime_settings2['upload']                 = '';
			$onetime_settings2['certificatecontents1']   = $certificate_contents;
			$onetime_settings2['certificateuploadtime1'] = $timestamp;
			$onetime_settings2['certificatename1']       = $timestamp . ': ' . $certificate_name;
			$onetime_settings2['selectcertificate']      = '1';
			$onetime_settings2['choosecertificate']      = '';
			$onetime_settings2['currency']               = $keys['currency'];
			$onetime_settings2['culture']                = $keys['culture'];
			$onetime_settings2['debugmode']              = 'off';
			$onetime_settings2['transactiondescription'] = $keys['transactiondescription'];

			if ( ! get_option( 'woocommerce_buckaroo_mastersettings_settings' ) ) {
				add_option( 'woocommerce_buckaroo_mastersettings_settings', $onetime_settings2 );
			} else {
				update_option( 'woocommerce_buckaroo_mastersettings_settings', $onetime_settings2 );
			}

			add_option( 'woocommerce_buckaroo_exodus', array( 'covenant' => true ) );

			echo json_encode( 'Migration complete, please refresh the page. For improved security, you can also delete your Buckaroo certificate from your certificate folder.' );
		} else {
			echo 'Settings could not be migrated.';
		}
		exit();
	}
}
