<?php

namespace Buckaroo\Woocommerce\PaymentProcessors;


use Buckaroo\Resources\Constants\ResponseStatus;
use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressUpdateOrderAddresses;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\RefundAction;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
use Buckaroo\Woocommerce\SDK\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use WC_Order;

class PushProcessor
{
    protected function onSuccess($order_id, $order, ResponseParser $responseParser)
    {
        global $woocommerce, $wpdb;

        if (!session_id()) {
            @session_start();
        }

        //Logger
        if (in_array($order->get_status(), array('completed', 'processing'))) {
            Logger::log(
                'Push message. Order already in final state or have the same status as response. Order status: ' . $order->get_status()
            );

            switch ($responseParser->get('status')) {
                case 'completed':
                    return array(
                        'result' => 'success',
                        'redirect' => $order->get_checkout_order_received_url(),
                    );
                    break;
                default:
                    return;
            }
        } else {
            switch ($responseParser->get('status')) {
                case 'completed':
                    /** handle klarnakp reservation push */
                    if (
                        $responseParser->getService('reservationNumber') !== null &&
                        $order->get_status() !== 'cancelled'
                    ) {
                        $order->payment_complete($responseParser->getTransactionKey());
                        $order->add_order_note(
                            "Payment succesfully reserved"
                        );
                        $order->add_meta_data('buckaroo_is_reserved', 'yes', true);
                        $order->save_meta_data();
                        return;
                    }


                    $transaction = $responseParser->getTransactionKey();
                    $payment_methodname = $responseParser->getPaymentMethod();
                    if ($responseParser->getRelatedTransactionPartialPayment() != null) {
                        $transaction = $responseParser->getRelatedTransactionPartialPayment();
                        $payment_methodname = 'grouptransaction';
                    }

                    if ((int)$order_id > 0) {
                        $row = $wpdb->get_row(
                            "SELECT wc_orderid FROM {$wpdb->prefix}woocommerce_buckaroo_transactions WHERE wc_orderid = " . intval($order_id)
                        );
                        if (empty($row->wc_orderid)) {
                            $wpdb->query(
                                $wpdb->prepare(
                                    "
                        INSERT INTO {$wpdb->prefix}woocommerce_buckaroo_transactions VALUES (" . intval(
                                        $order_id
                                    ) . ", %s)",
                                    $transaction
                                )
                            );
                        }
                    }
                    $clean_order_no = (int)str_replace('#', '', $order_id);
                    add_post_meta($clean_order_no, '_payment_method_transaction', $payment_methodname, true);

                    // Calc total received amount
                    $prefix = "buckaroo_settlement_";
                    $settlement = $prefix . $responseParser->getPaymentKey();

                    $orderAmount = Helper::roundAmount($order->get_total());
                    $paidAmount = Helper::roundAmount($responseParser->getAmount());
                    $alreadyPaidSettlements = 0;
                    $isNewPayment = true;
                    if ($items = get_post_meta($order_id)) {
                        foreach ($items as $key => $meta) {
                            if (strstr($key, $prefix) !== false && strstr($key, $responseParser->getPaymentKey()) === false) {
                                $alreadyPaidSettlements += (float)$meta[0];
                            }

                            // check if push is a new payment
                            if (strstr($key, $prefix) !== false && strstr($key, $responseParser->getPaymentKey()) !== false) {
                                $isNewPayment = false;
                            }
                        }
                    }

                    $totalPaid = $paidAmount + $alreadyPaidSettlements;

                    add_post_meta($order_id, $settlement, $paidAmount, true);

                    // order is completely paid
                    if ($totalPaid >= $orderAmount) {
                        $order->payment_complete($transaction);
                    }

                    $message = 'Received Buckaroo payment push notification.<br>';
                    $message .= 'Paid amount: ' . wc_price($paidAmount);
                    $message .= '<br>Total amount paid (incl previous payments): ' . wc_price(($totalPaid));
                    $message .= '<br>Order total: ' . wc_price($orderAmount);
                    $message .= '<br>Open amount: ' . wc_price(($orderAmount - $totalPaid));

                    if ($paidAmount > 0 && $isNewPayment) {
                        $order->add_order_note($message);
                    }

                    add_post_meta($order_id, '_pushallowed', 'ok', true);

                    break;
                default:
                    Logger::log('Update status 1. Order status: on-hold');
                    $order->update_status('on-hold', __($responseParser->getSubCodeMessage(), 'wc-buckaroo-bpe-gateway'));
                    // Reduce stock levels
                    break;
            }
            // Remove cart
            $woocommerce->cart->empty_cart();
            if ($val = $responseParser->get('ConsumerMessage.HtmlText')) {
                $_SESSION['buckaroo_response'] = $val;
            }
            // Return thank you page redirect
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_order_received_url(),
            );
        }
    }

    protected function parsePPENewTransactionId($transactions)
    {
        return !empty($transactions) ? explode(',', $transactions) : '';
    }

    protected function metaUpdate($order_id, $order, ResponseParser $responseParser)
    {
        if (strtolower($order->get_payment_method()) === 'buckaroo_payperemail') {
            $transactionsArray = $this->parsePPENewTransactionId($responseParser->getTransactionKey());
            if (!empty($transactionsArray) && $responseParser->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_SUCCESS) {
                $creditcardProvider = Helper::checkCreditCardProvider($responseParser->getPaymentMethod());
                update_post_meta($order_id, '_transaction_id', $transactionsArray[count($transactionsArray) - 1]);
                if ($creditcardProvider) {
                    update_post_meta($order_id, '_payment_method', 'buckaroo_creditcard');
                    update_post_meta($order_id, '_payment_method_title', 'Creditcards');
                    update_post_meta($order_id, '_payment_method_transaction', $responseParser->getPaymentMethod());
                    update_post_meta($order_id, '_wc_order_payment_issuer', $responseParser->getPaymentMethod());
                } else {
                    update_post_meta($order_id, '_payment_method', 'buckaroo_' . strtolower($responseParser->getPaymentMethod()));
                    $responsePaymentMethod = $responseParser->getPaymentMethod() !== 'payperemail' ? ' + ' . $responseParser->getPaymentMethod() : '';
                    update_post_meta($order_id, '_payment_method_title', 'PayperEmail' . $responsePaymentMethod);
                    update_post_meta($order_id, '_payment_method_transaction', $responseParser->getPaymentMethod());
                }
            }
        } elseif (strtolower($order->get_payment_method()) === 'buckaroo_sepadirectdebit' && $responseParser->getPaymentMethod() === 'payperemail') {
            return false;
        }
        return true;
    }

    public function handle()
    {
        global $woocommerce, $wpdb;

        if (!session_id()) {
            @session_start();
        }
        $_SESSION['buckaroo_response'] = '';
        Logger::log("Return start / fn_buckaroo_process_response_push");

        $responseParser = ResponseRegistry::getResponse($_POST ?? $_GET);

        Logger::log('Parse response:\n', $responseParser);

        $order_id = $responseParser->getRealOrderId() ?: $responseParser->getOrderNumber() ?: $responseParser->getInvoice();

        Logger::log(__METHOD__ . "|5|", $order_id);

        $order = new WC_Order($order_id);
        if ((int)$order_id > 0) {
            $order = new WC_Order($order_id);
//            if (!isset($GLOBALS['plugin_id'])) {
//                $GLOBALS['plugin_id'] = $payment_method->plugin_id . $order->get_payment_method() . "_settings";
//            }
        }

        $buckarooClient = new BuckarooClient($responseParser->isTest() ? 'test' : 'live');

        if ($buckarooClient->isReplyHandlerValid($responseParser->get(formatted: false))) {
            //Check if redirect required
            $checkIfRedirectRequired = Helper::processCheckRedirectRequired($responseParser);
            if ($checkIfRedirectRequired) {
                return $checkIfRedirectRequired;
            }

            if ($responseParser->getPaymentMethod() == 'paypal') {
                (new PaypalExpressUpdateOrderAddresses($order, $responseParser))->update();
            }

            $giftCardPartialPayment = ($responseParser->isAwaitingConsumer() && $responseParser->getTransactionType() == 'I150');

            if ($responseParser->getRelatedTransactionPartialPayment() != null || $giftCardPartialPayment) {
                Logger::log('PUSH', "Partial payment PUSH received " . $responseParser->getStatusCode());
                exit();
            }
            if ($responseParser->getRefundParentKey() != null) {
                RefundAction::initiateExternalServiceRefund($order_id, $responseParser);
            }
            Logger::log('Order status: ' . $order->get_status());
            if ($responseParser->isOnHold() && ($order->get_payment_method() == 'buckaroo_paypal')) {
                $responseParser->set('status', BuckarooTransactionStatus::STATUS_CANCELLED);
            }
            Logger::log('Response order status: ' . $responseParser->get('status'));
            Logger::log('Status message: ' . $responseParser->getSubCodeMessage());

            if (!$this->metaUpdate($order_id, $order, $responseParser)) {
                return;
            }

            if ($responseParser->isSuccess()) {
                $this->onSuccess($order_id, $order, $responseParser);

            } else {

                if ($responseParser->get('status') == BuckarooTransactionStatus::STATUS_ON_HOLD && $order->get_payment_method() == 'buckaroo_in3') {
                    return;
                }
                if (in_array($order->get_payment_method(), ['buckaroo_payperemail', 'buckaroo_transfer'])) {
                    Logger::log('Payperemail status check: ' . $responseParser->getStatusCode());
                    if (Helper::handleUnsuccessfulPayment($responseParser->getStatusCode())) return;
                }

                Logger::log('Payment request failed/canceled. Order status: ' . $order->get_status());
                if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled'))) {
                    //We receive a valid response that the payment is canceled/failed.
                    Logger::log('Update status 2. Order status: failed');
                    $order->update_status('failed', __($responseParser->getSubCodeMessage(), 'wc-buckaroo-bpe-gateway'));
                } else {
                    Logger::log('Push message. Order status cannot be changed.');
                }
                if ($responseParser->get('status') == BuckarooTransactionStatus::STATUS_CANCELLED) {
                    Logger::log('Update status 3. Order status: cancelled');
                    if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled'))) {
                        $order->update_status('cancelled', __($responseParser->getSubCodeMessage(), 'wc-buckaroo-bpe-gateway'));
                    } else {
                        Logger::log('Push message. Order status cannot be changed.');
                    }
                    wc_add_notice(__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'), 'error');
                } else {
                    if ($responseParser->getPaymentMethod() == 'afterpaydigiaccept' && $responseParser->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_REJECTED) {
                        wc_add_notice(
                            __(
                                "We are sorry to inform you that the request to pay afterwards with Riverty is not possible at this time. This can be due to various (temporary) reasons. For questions about your rejection you can contact the customer service of Riverty. Or you can visit the website of Riverty and check the 'Frequently asked questions' through this <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord\" target=\"_blank\">link</a>. We advise you to choose another payment method to complete your order.",
                                'wc-buckaroo-bpe-gateway'
                            ),
                            'error'
                        );
                    } else {
                        wc_add_notice(
                            __(
                                'Payment unsuccessful. Please try again or choose another payment method.',
                                'wc-buckaroo-bpe-gateway'
                            ),
                            'error'
                        );
                    }
                }
                return;
            }
        } else {
            Logger::log('Response not valid!');
            Logger::log('Parse response:\n', $responseParser);
            if ($responseParser->getPaymentMethod() == 'afterpaydigiaccept' && $responseParser->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_REJECTED) {
                wc_add_notice(
                    __(
                        "We are sorry to inform you that the request to pay afterwards with Riverty is not possible at this time. This can be due to various (temporary) reasons. For questions about your rejection you can contact the customer service of Riverty. Or you can visit the website of Riverty and check the 'Frequently asked questions' through this <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord\" target=\"_blank\">link</a>. We advise you to choose another payment method to complete your order.",
                        'wc-buckaroo-bpe-gateway'
                    ),
                    'error'
                );
            } else {
                wc_add_notice(
                    __(
                        'Payment unsuccessful. Please try again or choose another payment method.',
                        'wc-buckaroo-bpe-gateway'
                    ),
                    'error'
                );
            }
            return;
        }
        return;
    }
}