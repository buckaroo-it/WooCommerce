<?php

namespace Buckaroo\Woocommerce\PaymentProcessors;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use Exception;
use WC_Order;

class ReturnProcessor {

    protected $data                 = array();
    protected $validateReplyHandler = true;

    public function __construct( array $data = array(), $validateReplyHandler = true ) {
        $this->data                 = $data ?: $this->data;
        $this->validateReplyHandler = $validateReplyHandler;
    }

    public function handle( AbstractPaymentGateway $paymentGateway ) {
        global $woocommerce;

        if ( ! session_id() ) {
            @session_start();
        }
        $responseParser = ResponseRegistry::getResponse( $this->data );

        $_SESSION['buckaroo_response'] = '';

        Logger::log( __METHOD__, var_export( $_SERVER, true ) );
        Logger::log( __METHOD__, $responseParser );

        $orderId = $this->getOrderId( $responseParser );
        $order   = $this->getOrder( $orderId, $paymentGateway );

        if ( ! $order ) {
            Logger::log( __METHOD__ . '|10|' );
            return;
        }

        // Validate signature if needed
        $buckarooClient = new BuckarooClient( $paymentGateway->getMode() );

        if ( $this->validateReplyHandler ) {
            if ( ! $buckarooClient->isReplyHandlerValid( $this->data ) ) {
                Logger::log( 'Response not valid for order. Signature failed. Order id: ' . ( $orderId ?: 'order not created' ) );
                Logger::log( 'Response not valid!', $responseParser );
                return;
            }
        }

        update_post_meta( $orderId, '_buckaroo_order_in_test_mode', $responseParser->isTest() );

        // Check if we need to redirect first (based on the response)
        if ( $redirect = Helper::processCheckRedirectRequired( $responseParser ) ) {
            return $redirect;
        }

        Logger::log( __METHOD__ . '|20|', array( $orderId, $responseParser->getPaymentMethod(), $responseParser->isSuccess() ) );
        Logger::log( 'Order status: ' . $order->get_status() );

        $gatewayProcessor = $paymentGateway->newPaymentProcessorInstance( $order );

        // Call gateway-specific logic before handling returns
        if ( method_exists( $gatewayProcessor, 'beforeReturnHandler' ) ) {
            $response = $gatewayProcessor->beforeReturnHandler( $responseParser, $this->getRedirectUrl( $paymentGateway, $order ) );

            if ( isset( $response['result'] ) ) {
                return $response;
            }
        }

        Logger::log( 'Response order status: ' . $responseParser->get( 'coreStatus' ) );
        Logger::log( 'Status message: ' . $responseParser->getSubCodeMessage() );

        // Handle success
        if ( $responseParser->isSuccess() ) {
            return $this->handleSuccessfulPayment( $order, $paymentGateway, $responseParser, $woocommerce );
        }

        Logger::log( 'infoLog ' . $responseParser->get( 'coreStatus' ) );

        // Call gateway-specific logic on unsuccessful returns
        if ( method_exists( $gatewayProcessor, 'unsuccessfulReturnHandler' ) ) {
            $response = $gatewayProcessor->unsuccessfulReturnHandler( $responseParser, $this->getRedirectUrl( $paymentGateway, $order, 'error' ) );

            if ( isset( $response['result'] ) ) {
                return $response;
            }
        }

        // Update order status: failed/cancelled
        $this->updateStatusFailedOrCancelled( $order, $responseParser );

        // Show notice
        $errorDescription = 'Payment unsuccessful. Please try again or choose another payment method.';
        wc_add_notice( __( $errorDescription, 'wc-buckaroo-bpe-gateway' ), 'error' );
        $this->maybeAddNlSpecificError( $responseParser, $order, $errorDescription );

        // Redirect
        return $this->handleFailureRedirect( $paymentGateway, $order, $responseParser, $errorDescription );
    }

    /**
     * Handle success scenarios.
     */
    protected function handleSuccessfulPayment( $order, $paymentGateway, ResponseParser $responseParser, $woocommerce ) {
        switch ( $responseParser->get( 'coreStatus' ) ) {
            case 'completed':
            case 'processing':
            case 'pending':
            case 'on-hold':
                $woocommerce->cart->empty_cart();
                return array(
                    'result'   => 'success',
                    'redirect' => $this->getRedirectUrl( $paymentGateway, $order ),
                );
        }

        return null;
    }

    /**
     * Update order to failed or cancelled if it's not already in a final state.
     */
    protected function updateStatusFailedOrCancelled( $order, ResponseParser $responseParser ) {
        if ( ! $this->canUpdateStatus( $order ) ) {
            Logger::log( 'Order status cannot be changed.' );
            return;
        }

        Logger::log( 'Update status: failed' );
        $order->update_status( 'failed', __( $responseParser->getSubCodeMessage(), 'wc-buckaroo-bpe-gateway' ) );

        if ( $responseParser->isCanceled() ) {
            Logger::log( 'Update status: cancelled' );
            if ( $this->canUpdateStatus( $order ) ) {
                $order->update_status( 'cancelled', __( $responseParser->getSubCodeMessage(), 'wc-buckaroo-bpe-gateway' ) );
            } else {
                Logger::log( 'Response. Order status cannot be changed.' );
            }
            wc_add_notice( __( 'Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway' ), 'error' );
        }
    }

    /**
     * Decide whether order status can be updated (not in completed, processing, cancelled, failed, or refund).
     */
    protected function canUpdateStatus( $order ) {
        return ! in_array( $order->get_status(), array( 'completed', 'processing', 'cancelled', 'failed', 'refund' ), true );
    }

    /**
     * Handles final redirect in case of failure.
     */
    protected function handleFailureRedirect( $paymentGateway, $order, $responseParser, $defaultMessage ) {
        if ( $paymentGateway->get_failed_url() ) {
            $url        = $this->getRedirectUrl( $paymentGateway, $order, 'error' );
            $encodedMsg = base64_encode( $this->parseErrorMessage( $responseParser, $order, $defaultMessage ) );
            return array( 'redirect' => $url . '?bck_err=' . $encodedMsg );
        }
        return array( 'redirect' => $this->getRedirectUrl( $paymentGateway, $order, 'error' ) );
    }

    protected function getRedirectUrl( $paymentGateway, $order, $type = 'success' ) {
        if ( is_admin() ) {
            return wp_get_referer() ?: $order->get_edit_order_url();
        }

        return $type === 'success'
            ? $paymentGateway->get_return_url( $order )
            : $paymentGateway->get_failed_url();
    }

    protected function getOrderId( ResponseParser $responseParser ) {
        $orderId = $responseParser->getOrderNumber() ?: $responseParser->getInvoice();
        if ( is_int( $responseParser->getRealOrderId() ) ) {
            $orderId = $responseParser->getRealOrderId();
        }
        return $orderId;
    }

    protected function getOrder( $orderId, $paymentGateway ) {
        try {
            $order = new WC_Order( $orderId );
            if ( $orderId > 0 && ! isset( $GLOBALS['plugin_id'] ) ) {
                $GLOBALS['plugin_id'] = $paymentGateway->plugin_id . $order->get_payment_method() . '_settings';
            }
            return $order;
        } catch ( Exception $e ) {
            Logger::log( __METHOD__ . '|10|', $e->getMessage() );
            return null;
        }
    }

    protected function maybeAddNlSpecificError( ResponseParser $responseParser, $order, &$defaultMessage ) {
        if ( ! $this->isNlOrder( $order ) ) {
            return;
        }
        $subCodeMessage = $responseParser->getSubCodeMessage();
        if ( strrpos( $subCodeMessage, ': ' ) !== false ) {
            $defaultMessage = str_replace( ':', '', substr( $subCodeMessage, strrpos( $subCodeMessage, ': ' ) ) );
            wc_add_notice( __( $defaultMessage, 'wc-buckaroo-bpe-gateway' ), 'error' );
        }
    }

    protected function parseErrorMessage( ResponseParser $responseParser, $order, $defaultMessage ) {
        if ( ! $this->isNlOrder( $order ) ) {
            return $defaultMessage;
        }
        $subCodeMessage = $responseParser->getSubCodeMessage();
        if ( strrpos( $subCodeMessage, ': ' ) !== false ) {
            return str_replace( ':', '', substr( $subCodeMessage, strrpos( $subCodeMessage, ': ' ) ) );
        }
        return $defaultMessage;
    }

    protected function isNlOrder( $order ) {
        if ( Helper::isWooCommerceVersion3OrGreater() ) {
            return $order->get_billing_country() === 'NL';
        }
        return isset( $order->billing_country ) && $order->billing_country === 'NL';
    }
}
