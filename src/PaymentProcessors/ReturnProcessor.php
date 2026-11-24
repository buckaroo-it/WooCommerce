<?php

namespace Buckaroo\Woocommerce\PaymentProcessors;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\Transfer\TransferResponse;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\CheckoutError;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\LoggerStorage;
use Exception;
use WC_Order;

class ReturnProcessor
{
    protected $data = [];

    protected $validateReplyHandler = true;

    public function __construct(array $data = [], $validateReplyHandler = true)
    {
        $this->data = $data ?: $this->data;
        $this->validateReplyHandler = $validateReplyHandler;
    }

    public function handle(AbstractPaymentGateway $paymentGateway)
    {
        global $woocommerce;

        if (! session_id()) {
            @session_start();
        }
        $responseParser = ResponseRegistry::getResponse($this->data);

        $_SESSION['buckaroo_response'] = '';

        Logger::log(__METHOD__, LoggerStorage::request_context());
        Logger::log(__METHOD__, $responseParser);

        $orderId = $this->getOrderId($responseParser);
        $order = $this->getOrder($orderId, $paymentGateway);

        if (! $order) {
            Logger::log(__METHOD__ . '|10|');

            return $this->handleFailureRedirect($paymentGateway, $order);
        }

        // Validate signature if needed
        $buckarooClient = new BuckarooClient($paymentGateway->getMode());

        if ($this->validateReplyHandler) {
            if (! $buckarooClient->isReplyHandlerValid($this->data)) {
                Logger::log('Response not valid for order. Signature failed. Order id: ' . ($orderId ?: 'order not created'));
                Logger::log('Response not valid!', $responseParser);

                return $this->handleFailureRedirect($paymentGateway, $order);
            }

            if (! $responseParser->hasValidTransactionKeys()) {
                Logger::log('Return ignored: a transaction key has an invalid format. Order id: ' . $orderId);

                return $this->handleFailureRedirect($paymentGateway, $order);
            }
        }

        // A browser return can be replayed, so it is matched to the order. The
        // synchronous response of PayAction is not validated and starts the
        // order's current transaction.
        $staleReply = false;
        if ($this->validateReplyHandler) {
            if (PushProcessor::replyMismatch($order, $responseParser) !== null) {
                Logger::log('Return ignored: currency or mode does not match the order. Order id: ' . $orderId);

                return $this->handleFailureRedirect($paymentGateway, $order);
            }

            $staleReply = PushProcessor::isStaleReply($order, $responseParser);
        }

        (new TransferResponse($responseParser))->storeBankDetails($order);

        if (! $staleReply) {
            $order->set_transaction_id($responseParser->getTransactionKey());
            $order->save();

            OrderMeta::update($order, '_buckaroo_order_in_test_mode', $responseParser->isTest());
        }

        // Check if we need to redirect first (based on the response)
        if ($redirect = Helper::processCheckRedirectRequired($responseParser)) {
            return $redirect;
        }

        Logger::log(__METHOD__ . '|20|', [$orderId, $responseParser->getPaymentMethod(), $responseParser->isSuccess()]);
        Logger::log('Order status: ' . $order->get_status());

        $gatewayProcessor = $paymentGateway->newPaymentProcessorInstance($order);

        // Call gateway-specific logic before handling returns
        if (method_exists($gatewayProcessor, 'beforeReturnHandler')) {
            $response = $gatewayProcessor->beforeReturnHandler($responseParser, $this->getRedirectUrl($paymentGateway, $order));

            if (isset($response['result'])) {
                return $response;
            }
        }

        Logger::log('Response order status: ' . $responseParser->get('coreStatus'));
        Logger::log('Status message: ' . $responseParser->getSubCodeMessage());

        // Handle success
        if ($responseParser->isSuccess()) {
            return $this->handleSuccessfulPayment($order, $paymentGateway, $responseParser, $woocommerce);
        }

        Logger::log('infoLog ' . $responseParser->get('coreStatus'));

        // Call gateway-specific logic on unsuccessful returns
        if (method_exists($gatewayProcessor, 'unsuccessfulReturnHandler')) {
            $response = $gatewayProcessor->unsuccessfulReturnHandler($responseParser, $this->getRedirectUrl($paymentGateway, $order, 'error'));

            if (isset($response['result'])) {
                return $response;
            }
        }

        // Update order status: failed/cancelled
        if ($staleReply) {
            Logger::log('Return for transaction ' . $responseParser->getTransactionKey() . ' is not the order\'s current transaction. Order status not changed.');
        } else {
            $this->updateStatusFailedOrCancelled($order, $responseParser);
        }

        // Show notice
        $errorDescription = __('Payment unsuccessful. Please try again or choose another payment method.', 'wc-buckaroo-bpe-gateway');
        wc_add_notice(__('Payment unsuccessful. Please try again or choose another payment method.', 'wc-buckaroo-bpe-gateway'), 'error');
        $this->maybeAddNlSpecificError($responseParser, $order, $errorDescription);

        // Redirect
        return $this->handleFailureRedirect(
            $paymentGateway,
            $order,
            $responseParser->isCanceled() ? CheckoutError::PAYMENT_CANCELLED : CheckoutError::PAYMENT_FAILED
        );
    }

    /**
     * Handle success scenarios.
     */
    protected function handleSuccessfulPayment($order, $paymentGateway, ResponseParser $responseParser, $woocommerce)
    {
        switch ($responseParser->get('coreStatus')) {
            case 'completed':
            case 'processing':
            case 'pending':
            case 'on-hold':
                $woocommerce->cart->empty_cart();

                return [
                    'result' => 'success',
                    'redirect' => $this->getRedirectUrl($paymentGateway, $order),
                ];
        }

        return $this->handleFailureRedirect($paymentGateway, $order);
    }

    /**
     * Update order to failed or cancelled if it's not already in a final state.
     */
    protected function updateStatusFailedOrCancelled($order, ResponseParser $responseParser)
    {
        if (! $this->canUpdateStatus($order) || $this->isOrderFullyPaid($order)) {
            if ($this->isOrderFullyPaid($order)) {
                Logger::log('Order was previously fully paid - status cannot be changed to failed.');
            } else {
                Logger::log('Order status cannot be changed.');
            }

            return;
        }

        Logger::log('Update status: failed');
        $order->update_status('failed', $responseParser->getSubCodeMessage());

        if ($responseParser->isCanceled()) {
            Logger::log('Payment cancelled by customer - keeping status as failed to allow retry');
            // Keep order as 'failed' instead of 'cancelled' to allow customer to retry payment
            // WooCommerce only allows 'pending' or 'failed' orders to be retried
            wc_add_notice(__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'), 'error');
        }
    }

    /**
     * Decide whether order status can be updated (not in completed, processing, cancelled, failed, or refund).
     */
    protected function canUpdateStatus($order)
    {
        return ! in_array($order->get_status(), ['completed', 'processing', 'cancelled', 'failed', 'refund'], true);
    }

    protected function isOrderFullyPaid($order)
    {
        return !empty($order->get_date_paid());
    }

    /**
     * Handles final redirect in case of failure.
     */
    protected function handleFailureRedirect($paymentGateway, $order, string $errorCode = CheckoutError::PAYMENT_FAILED)
    {
        return ['result' => 'failure', 'redirect' => $this->getRedirectUrl($paymentGateway, $order, 'error', $errorCode)];
    }

    protected function getRedirectUrl($paymentGateway, $order, $type = 'success', string $errorCode = '')
    {
        if ($type === 'success') {
            return $paymentGateway->get_return_url($order);
        }

        $failedUrl = $paymentGateway->get_failed_url();

        return $errorCode !== '' ? CheckoutError::addToUrl($failedUrl, $errorCode) : $failedUrl;
    }

    protected function getOrderId(ResponseParser $responseParser)
    {
        $orderId = $responseParser->getOrderNumber() ?: $responseParser->getInvoice();
        if (!is_null($responseParser->getRealOrderId())) {
            $orderId = $responseParser->getRealOrderId();
        }

        return $orderId;
    }

    protected function getOrder($orderId, $paymentGateway)
    {
        try {
            $order = new WC_Order($orderId);
            if ($orderId > 0 && ! isset($GLOBALS['plugin_id'])) {
                $GLOBALS['plugin_id'] = $paymentGateway->plugin_id . $order->get_payment_method() . '_settings';
            }

            return $order;
        } catch (Exception $e) {
            Logger::log(__METHOD__ . '|10|', $e->getMessage());

            return null;
        }
    }

    protected function maybeAddNlSpecificError(ResponseParser $responseParser, $order, &$defaultMessage)
    {
        if (! $this->isNlOrder($order)) {
            return;
        }
        $subCodeMessage = $responseParser->getSubCodeMessage();
        if ($subCodeMessage && strrpos($subCodeMessage, ': ') !== false) {
            $defaultMessage = str_replace(':', '', substr($subCodeMessage, strrpos($subCodeMessage, ': ')));
            wc_add_notice($defaultMessage, 'error');
        }
    }

    protected function isNlOrder($order)
    {
        // WooCommerce 3.0+ uses getter methods, which is required for HPOS compatibility
        return $order->get_billing_country() === 'NL';
    }
}
