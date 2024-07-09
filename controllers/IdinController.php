<?php
require_once __DIR__ . '/../library/include.php';
require_once __DIR__ . '/../library/api/paymentmethods/paymentmethod.php';

class IdinController {


	public function returnHandler() {
		$post_data = wc_clean($_POST);
		$response = new BuckarooResponseDefault($post_data);

		if ($response && $response->isValid() && $response->hasSucceeded()) {
			$bin = !empty($post_data['brq_SERVICE_idin_ConsumerBIN']) ? $post_data['brq_SERVICE_idin_ConsumerBIN'] : 0;
			$isEighteen = isset($post_data['brq_SERVICE_idin_IsEighteenOrOlder']) && $post_data['brq_SERVICE_idin_IsEighteenOrOlder'] === 'True';

			if ($isEighteen) {
				BuckarooIdin::setCurrentUserIsVerified($bin);
				wc_add_notice(__('You have been verified successfully', 'wc-buckaroo-bpe-gateway'), 'success');
			} else {
				wc_add_notice(__('According to iDIN you are under 18 years old', 'wc-buckaroo-bpe-gateway'), 'error');
			}
		} else {
			wc_add_notice(
				empty($response->statusmessage) ?
					__('Verification has been failed', 'wc-buckaroo-bpe-gateway') : stripslashes($response->statusmessage),
				'error'
			);
		}

		if (!empty($_REQUEST['bk_redirect']) && is_string($_REQUEST['bk_redirect'])) {
			wp_safe_redirect($_REQUEST['bk_redirect']);
			exit;
		}
	}

	public function identify() {
		if ( ! BuckarooConfig::isIdin( BuckarooIdin::getCartProductIds() ) ) {
			$this->sendError( esc_html__( 'iDIN is disabled' ) );
		}

		$data                 = array();
		$data['currency']     = 'EUR';
		$data['amountDebit']  = 0;
		$data['amountCredit'] = 0;
		$data['mode']         = BuckarooConfig::getIdinMode();
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer           = sanitize_text_field( $_SERVER['HTTP_REFERER'] );
			$url               = parse_url( $referer );
			$data['returnUrl'] = $url['scheme'] . '://' . $url['host'] . '/' . ( $url['path'] ?? '' ) .
				'?wc-api=WC_Gateway_Buckaroo_idin-return&bk_redirect=' . urlencode( $referer );
		}
		$data['continueonincomplete'] = 'redirecttohtml';

		$data['services']['idin']['action']  = 'verify';
		$data['services']['idin']['version'] = '0';

		$issuer = '';
		if ( isset( $_GET['issuer'] ) && is_string( $_GET['issuer'] ) ) {
			$issuer = sanitize_text_field( $_GET['issuer'] );
		}
		$data['customVars']['idin']['issuerId'] = BuckarooIdin::checkIfValidIssuer( $issuer ) ? $issuer : '';

		$soap = new BuckarooSoap( $data );

		$response = BuckarooResponseFactory::getResponse( $soap->transactionRequest( 'DataRequest' ) );

//		Buckaroo_Logger::log( __METHOD__ . '|5|', $response );

		$processedResponse = fn_buckaroo_process_response( null, $response );

//		Buckaroo_Logger::log( __METHOD__ . '|10|', $processedResponse );

		wp_send_json( $processedResponse );
	}

	public function reset() {
		BuckarooIdin::setCurrentUserIsNotVerified();

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	private function sendError( $error ) {
		wp_send_json(
			array(
				'result'  => 'error',
				'message' => $error,
			)
		);
	}
}
