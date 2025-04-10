<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Logger;
use WC_Order;
use WP_Error;

class RefundAction {

	protected ?string $originalTransactionKey;
	protected AbstractRefundProcessor $paymentProcessor;
	protected BuckarooClient $buckarooClient;
	private WC_Order $order;

	public function __construct( AbstractRefundProcessor $paymentProcessor, $orderId, ?string $originalTransactionKey ) {
		$this->paymentProcessor       = $paymentProcessor;
		$this->order                  = new WC_Order( $orderId );
		$this->buckarooClient         = new BuckarooClient( $this->paymentProcessor->gateway->getMode() );
		$this->originalTransactionKey = $originalTransactionKey;
	}

	public static function initiateExternalServiceRefund( $order_id, ResponseParser $responseParser ) {
		Logger::log( 'PUSH', 'Refund payment PUSH received ' . $responseParser->get( 'coreStatus' ) );
		$allowedPush = get_post_meta( $order_id, '_pushallowed', true );
		Logger::log( __METHOD__ . '|10|', $allowedPush );
		if ( $responseParser->isSuccess() && $allowedPush == 'ok' ) {
			$tmp = get_post_meta( $order_id, '_refundbuckaroo' . $responseParser->getTransactionKey(), true );
			if ( empty( $tmp ) ) {
				add_post_meta( $order_id, '_refundbuckaroo' . $responseParser->getTransactionKey(), 'ok', true );
				wc_create_refund(
					array(
						'amount'     => $responseParser->getAmountCredit(),
						'reason'     => __( 'Refunded', 'wc-buckaroo-bpe-gateway' ),
						'order_id'   => $order_id,
						'line_items' => array(),
					)
				);
			}
		}
		exit();
	}

	public function process() {
		$transactionResponse = $this->buckarooClient->process(
			$this->paymentProcessor,
			$this->originalTransactionKey ? array( 'originalTransactionKey' => $this->originalTransactionKey ) : array()
		);

		return $this->finalize( $transactionResponse );
	}

	public function finalize( TransactionResponse $transactionResponse ) {
		if ( $transactionResponse->isSuccess() ) {
			$this->order->add_order_note(
				sprintf(
					__( 'Refunded %1$s - Refund transaction ID: %2$s', 'wc-buckaroo-bpe-gateway' ),
					wc_price( $transactionResponse->get( 'AmountCredit' ) ),
					$transactionResponse->getTransactionKey()
				)
			);
			add_post_meta(
				$this->order->get_id(),
				'_refundbuckaroo' . $transactionResponse->getTransactionKey(),
				'ok',
				true
			);
			return true;
		}

		$this->order->add_order_note(
			sprintf(
				__(
					'Refund failed for transaction ID: %s ' . "\n" . $transactionResponse->getSomeError(),
					'wc-buckaroo-bpe-gateway'
				),
				$this->order->get_transaction_id()
			)
		);

		return new WP_Error( 'error_refund', __( 'Refund failed: ' ) . $transactionResponse->getSomeError() );
	}
}
