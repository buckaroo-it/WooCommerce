<?php

namespace Buckaroo\Woocommerce\PaymentProcessors;

use Buckaroo\Resources\Constants\ResponseStatus;
use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\GiftCard\GiftCardGateway;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use Exception;
use WC_Order;

class ReturnProcessor
{
    public function handle($payment_method = null)
    {
        global $woocommerce, $wpdb;

        $responseParser = ResponseRegistry::getResponse($_POST ?? $_GET);

        if (!session_id()) {
            @session_start();
        }

        $_SESSION['buckaroo_response'] = '';
        Logger::log(" Return start / fn_buckaroo_process_response");
        Logger::log("Server : " . var_export($_SERVER, true));
        Logger::log('Parse response:\n', $responseParser);

        if (empty($responseParser->getOrderNumber())) {
            $order_id = $responseParser->getInvoice();
        } else {
            $order_id = $responseParser->getOrderNumber();
        }

        if (is_int($responseParser->getRealOrderId())) {
            $order_id = $responseParser->getRealOrderId();
        }

        try {
            $order = new WC_Order($order_id);
            if ((int)$order_id > 0) {
                if (!isset($GLOBALS['plugin_id'])) {
                    $GLOBALS['plugin_id'] = $payment_method->plugin_id . $order->get_payment_method() . "_settings";
                }
            }
        } catch (Exception $e) {
            Logger::log(__METHOD__ . "|10|");
        }

        $buckarooClient = new BuckarooClient($payment_method->getMode());
        if ($buckarooClient->isReplyHandlerValid($responseParser->get(formatted: false))) {

            update_post_meta(
                $order_id,
                '_buckaroo_order_in_test_mode',
                $responseParser->isTest() == true
            );

            //Check if redirect required
            $checkIfRedirectRequired = Helper::processCheckRedirectRequired($responseParser);
            if ($checkIfRedirectRequired) {
                return $checkIfRedirectRequired;
            }

            Logger::log(__METHOD__ . "|20|", [$order_id, $responseParser->getPaymentMethod(), $responseParser->isSuccess()]);

            $process_response_idin = $this->fn_process_response_idin($responseParser, $order_id);
            if (is_array($process_response_idin)) {
                return $process_response_idin;
            }

            Logger::log('Order status: ' . $order->get_status());
            if (($responseParser->get('status') == BuckarooTransactionStatus::STATUS_ON_HOLD) && ($payment_method->id == 'buckaroo_paypal')) {
                $responseParser->set('status', BuckarooTransactionStatus::STATUS_CANCELLED);
            }
            Logger::log('Response order status: ' . $responseParser->get('status'));
            Logger::log('Status message: ' . $responseParser->getSubCodeMessage());

            //Payperemail response
            if ($this->fn_process_response_payperemail($payment_method, $responseParser, $order)) {
                return array(
                    'result' => 'success',
                    'redirect' => $payment_method->get_return_url($order),
                );
            }

            if ($order->get_payment_method() == 'buckaroo_klarnakp') {
                update_post_meta(
                    $order->get_id(),
                    '_buckaroo_klarnakp_reservation_number',
                    $responseParser->getService('reservationNumber')
                );
            }

            if ($responseParser->isSuccess()) {
                Logger::log(
                    'Order already in final state or  have the same status as response. Order status: ' . $order->get_status()
                );

                $this->addSepaDirectOrderNote($responseParser, $order);

                switch ($responseParser->get('status')) {
                    case 'completed':
                    case 'processing':
                    case 'pending':
                    case 'on-hold':
                        if (!is_null($payment_method)) {
                            $woocommerce->cart->empty_cart();
                            return array(
                                'result' => 'success',
                                'redirect' => $payment_method->get_return_url($order),
                            );
                        }
                        break;
                    default:
                        return;
                }
            } else {
                Logger::log('||| infoLog ' . $responseParser->get('status'));

                if ($responseParser->isPendingProcessing() && $order->get_payment_method() == 'buckaroo_in3') {
                    return;
                }
                if (in_array($order->get_payment_method(), ['buckaroo_payperemail', 'buckaroo_transfer'])) {
                    Logger::log('Payperemail status check: ' . $responseParser->getStatusCode());
                    if (Helper::handleUnsuccessfulPayment($responseParser->getStatusCode())) return;
                }
                if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled', 'failed', 'refund'))) {
                    //We receive a valid response that the payment is canceled/failed.
                    Logger::log('Update status 4. Order status: failed');
                    $order->update_status('failed', __($responseParser->getSubCodeMessage(), 'wc-buckaroo-bpe-gateway'));
                } else {
                    Logger::log('Order status cannot be changed.');
                }
                if ($responseParser->isCanceled()) {
                    Logger::log('Update status 5. Order status: cancelled');
                    if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled', 'failed', 'refund'))) {
                        $order->update_status('cancelled', __($responseParser->getSubCodeMessage(), 'wc-buckaroo-bpe-gateway'));
                    } else {
                        Logger::log('Response. Order status cannot be changed.');
                    }
                    wc_add_notice(__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'), 'error');
                } else {
                    if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled', 'failed', 'refund'))) {
                        Logger::log('Update status 6. Order status: failed');
                        $order->update_status('failed', __($responseParser->getSubCodeMessage(), 'wc-buckaroo-bpe-gateway'));
                    } else {
                        Logger::log('Order status cannot be changed.');
                    }
                    if ($responseParser->getPaymentMethod() == 'afterpaydigiaccept' && $responseParser->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_REJECTED) {
                        wc_add_notice(
                            __(
                                "We are sorry to inform you that the request to pay afterwards with Riverty is not possible at this time. This can be due to various (temporary) reasons. For questions about your rejection you can contact the customer service of Riverty. Or you can visit the website of Riverty and check the 'Frequently asked questions' through this <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord\" target=\"_blank\">link</a>. We advise you to choose another payment method to complete your order.",
                                'wc-buckaroo-bpe-gateway'
                            ),
                            'error'
                        );
                    } elseif ($payment_method instanceof GiftCardGateway && $responseParser->isFailed()) {
                        if ($responseParser->getSubCodeMessage() == 'Failed') {
                            wc_add_notice(
                                sprintf(
                                    __('Card number or pin is incorrect for %s', 'wc-buckaroo-bpe-gateway'),
                                    $responseParser->getPaymentMethod()
                                ),
                                'error'
                            );
                        } else {
                            wc_add_notice(
                                __($responseParser->getStatusMessage(), 'wc-buckaroo-bpe-gateway'),
                                'error'
                            );
                        }
                    } elseif (($responseParser->getPaymentMethod() == "afterpay") && ($responseParser->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_REJECTED)) {
                        wc_add_notice(
                            __(
                                $responseParser->getSubCodeMessage(),
                                'wc-buckaroo-bpe-gateway'
                            ),
                            'error'
                        );

                    } else {
                        Logger::log(__METHOD__ . "|50|");
                        $error_description = 'Payment unsuccessful. Please try again or choose another payment method.';
                        wc_add_notice(__($error_description, 'wc-buckaroo-bpe-gateway'), 'error');

                        Logger::log('wc session after: ' . var_export(WC()->session, true));
                        if (Helper::isWooCommerceVersion3OrGreater()) {
                            if ($order->get_billing_country() == 'NL') {
                                if (strrpos($responseParser->getSubCodeMessage(), ': ') !== false) {
                                    $error_description = str_replace(':', '', substr($responseParser->getSubCodeMessage(), strrpos($responseParser->getSubCodeMessage(), ': ')));
                                    Logger::log('||| failed status message: ' . $error_description);
                                    wc_add_notice(__($error_description, 'wc-buckaroo-bpe-gateway'), 'error');
                                }
                            }
                        } else {
                            if ($order->billing_country == 'NL') {
                                if (strrpos($responseParser->getSubCodeMessage(), ': ') !== false) {
                                    $error_description = str_replace(':', '', substr($responseParser->getSubCodeMessage(), strrpos($responseParser->getSubCodeMessage(), ': ')));
                                    wc_add_notice(__($error_description, 'wc-buckaroo-bpe-gateway'), 'error');
                                }
                            }
                        }
                        if ($payment_method && $payment_method->get_failed_url()) {
                            Logger::log(__METHOD__ . "|70|");
                            return [
                                'redirect' => $payment_method->get_failed_url() . '?bck_err=' . base64_encode($error_description)
                            ];
                        }
                    }
                }
                return [
                    'redirect' => $payment_method->get_failed_url()
                ];
            }
        } else {
            Logger::log(
                'Response not valid for order. Signature calculation failed. Order id: ' . (!empty($order_id) ? $order_id : 'order not created')
            );
            Logger::log('Response not valid!');
            Logger::log('Parse response:\n', $responseParser);

            return;
        }
    }

    protected function fn_process_response_payperemail($payment_method, ResponseParser $responseParser, $order)
    {
        if ($payment_method->id == 'buckaroo_payperemail') {
            Logger::log(__METHOD__, "Process paypermail");
            if (is_admin()) {
                if ($responseParser->isSuccess()) {
                    if (!$responseParser->get('customermessage')) {
                        $message = 'Your paylink: <a target="_blank" href="' . $responseParser->get('Services.Service.ResponseParameter') . '">' . $responseParser->get('Services.Service.ResponseParameter') . '</a>';
                        $order->add_order_note($message);
                        $buckaroo_admin_notice = array(
                            'type' => 'success',
                            'message' => $message
                        );
                    } else {
                        $message = 'Email sent successfully.<br>';
                        $order->add_order_note($message);
                    }
                } else {
                    $parameterError = '';
                    if ($responseParser->get('RequestErrors.ParameterError')) {
                        $parameterErrorArray = $responseParser->get('RequestErrors.ParameterError');
                        if (is_array($parameterErrorArray)) {
                            foreach ($parameterErrorArray as $key => $value) {
                                $parameterError .= '<br/>' . $value->_;
                            }
                        }
                    }
                    $buckaroo_admin_notice = array(
                        'type' => 'error',
                        'message' => $responseParser->getSubCodeMessage() . ' ' . $parameterError,
                    );
                }
                Logger::log(__METHOD__ . "|10|", $parameterError);

                set_transient(get_current_user_id() . 'buckarooAdminNotice', $buckaroo_admin_notice);
                return true;
            }
        }
    }

    protected function addSepaDirectOrderNote(ResponseParser $responseParser, $order)
    {
        if ($responseParser->getPaymentMethod() == 'SepaDirectDebit') {
            foreach ($responseParser->get('Services.Service.ResponseParameter') as $param) {
                if ($param->Name == 'MandateReference') {
                    $order->add_order_note('MandateReference: ' . $param->_, 1);
                }
                if ($param->Name == 'MandateDate') {
                    $order->add_order_note('MandateDate: ' . $param->_, 1);
                }
            }
        }
    }

    protected function fn_process_response_idin(ResponseParser $responseParser, $order_id = null)
    {
        if (!$order_id && ($responseParser->getPaymentMethod() == 'idin') && !$responseParser->isSuccess()) {
            Logger::log(__METHOD__ . "|25|");
            $message = '';
            if ($responseParser->getSubCodeMessage()) {
                $message = $responseParser->getSubCodeMessage();
            }
            Logger::log(__METHOD__ . "|30|", $message);

            return array(
                'result' => 'error',
                'message' => $message
            );
        } else {
            return false;
        }
    }

}