<?php
require_once dirname(__FILE__) . '/api/abstract.php';


/**
 * Can the order be refunded.
 *
 * @param  Object $response
 * @param  Object $order WC_Order
 * @param  String $amount
 * @param  String $currency
 * @return String|Boolean
 */
function fn_buckaroo_process_refund($response, $order, $amount, $currency)
{
    if ($response && $response->isValid() && $response->hasSucceeded()) {
        $order->add_order_note(
            sprintf(
                __('Refunded %1$s - Refund transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                $amount . ' ' . $currency,
                $response->transactions
            )
        );
        add_post_meta($order->get_order_number(), '_refundbuckaroo' . $response->transactions, 'ok', true);
        update_post_meta($order->get_order_number(), '_pushallowed', 'ok');
        return true;
    }
    if (!empty($response->ChannelError)) {
        Buckaroo_Logger::log(__METHOD__, $response->ChannelError);

        $order->add_order_note(
            sprintf(
                __(
                    'Refund failed for transaction ID: %s ' . "\n" . $response->ChannelError,
                    'wc-buckaroo-bpe-gateway'
                ),
                $order->get_transaction_id()
            )
        );
        update_post_meta($order->get_order_number(), '_pushallowed', 'ok');
        return new WP_Error('error_refund', __("Refund failed: ") . $response->ChannelError);
    } else {
        $order->add_order_note(
            sprintf(
                __('Refund failed for transaction ID: %s', 'wc-buckaroo-bpe-gateway'),
                $order->get_transaction_id()
            )
        );
        update_post_meta($order->get_order_number(), '_pushallowed', 'ok');
        return false;
    }
}

/**
 * Can the order be captured.
 *
 * @param  Object $response
 * @param  Object $order WC_Order
 * @param  String $amount
 * @param  String $currency
 * @return String|Boolean
 */
function fn_buckaroo_process_capture($response, $order, $currency, $products = null)
{

    if (!isset($_POST['capture_amount']) || !is_scalar($_POST['capture_amount'])) {
        return false;
    }

    $capture_amount = sanitize_text_field($_POST['capture_amount']);
    if ($response && $response->isValid() && $response->hasSucceeded()) {

        // SET the flags
        // check if order has already been captured
        if (get_post_meta($order->get_id(), '_wc_order_is_captured', true)) {

            // Order already captured
            // Add the other values of the capture so we have the full value captured
            $previousCaptures = (float) get_post_meta($order->get_id(), '_wc_order_amount_captured', true);
            $total            = $previousCaptures + (float) $capture_amount;
            update_post_meta($order->get_id(), '_wc_order_amount_captured', $total);

        } else {

            // Order not captured yet
            // Set first amout_captured and is_captured flag
            update_post_meta($order->get_id(), '_wc_order_is_captured', true);
            update_post_meta($order->get_id(), '_wc_order_amount_captured', $capture_amount);
        }

        $str        = "";
        $characters = range('0', '9');
        $max        = count($characters) - 1;
        for ($i = 0; $i < 2; $i++) {
            $rand = mt_rand(0, $max);
            $str .= $characters[$rand];
        }

        // Set the flag that contains all the items and taxes that have been captured
        add_post_meta($order->get_id(), '_wc_order_captures', array(
            'currency'             => $currency,
            'id'                   => $order->get_id() . $str,
            'amount'               => $capture_amount,
            'line_item_qtys'         => isset( $_POST['line_item_qtys'] ) ?  sanitize_text_field( wp_unslash( $_POST['line_item_qtys'] ), true ) :'',
            'line_item_totals'       => isset( $_POST['line_item_totals'] ) ?  sanitize_text_field( wp_unslash( $_POST['line_item_totals'] ), true ) :'',
            'line_item_tax_totals'   => isset( $_POST['line_item_tax_totals'] ) ?  sanitize_text_field( wp_unslash( $_POST['line_item_tax_totals'] ), true ) :'',
        ));

        add_post_meta($order->get_order_number(), '_capturebuckaroo' . $response->transactions, 'ok', true);
        update_post_meta($order->get_order_number(), '_pushallowed', 'ok');

        $order->add_order_note(
            sprintf(
                __('Captured %1$s - Capture transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                $capture_amount . ' ' . $currency,
                $response->transactions
            )
        );

        // Store the transaction_key together with captured products, we need this for refunding
        if ($products != null) {
            $capture_data = json_encode(['OriginalTransactionKey' => $response->transactions, 'products' => $products]);
            add_post_meta($order->get_id(), 'buckaroo_capture', $capture_data, false);
        }
        wp_send_json_success($response);

    }
    if (!empty($response->ChannelError)) {
        Buckaroo_Logger::log(__METHOD__, $response->ChannelError);
        $order->add_order_note(
            sprintf(
                __(
                    'Capture failed for transaction ID: %s ' . "\n" . $response->ChannelError,
                    'wc-buckaroo-bpe-gateway'
                ),
                $order->get_transaction_id()
            )
        );
        update_post_meta($order->get_order_number(), '_pushallowed', 'ok');
        return new WP_Error('error_capture', __("Capture failed: ") . $response->ChannelError);
    } else {
        $order->add_order_note(
            sprintf(
                __('Capture failed for transaction ID: %s', 'wc-buckaroo-bpe-gateway'),
                $order->get_transaction_id()
            )
        );
        update_post_meta($order->get_order_number(), '_pushallowed', 'ok');
        return false;
    }
}
/**
 * Process push response
 *
 * @param WC_Push_Buckaroo $payment_method
 * @param string $response
 *
 * @return void
 */
function fn_buckaroo_process_response_push($payment_method = null, $response = '')
{
    $woocommerce = getWooCommerceObject();
    $wpdb        = getWpdbObject();
    require_once dirname(__FILE__) . '/api/paymentmethods/responsefactory.php';
    if (!session_id()) {
        @session_start();
    }
    $_SESSION['buckaroo_response'] = '';
    Buckaroo_Logger::log("Return start / fn_buckaroo_process_response_push");
    if ($response == '') {
        $response = BuckarooResponseFactory::getResponse();
    }

    Buckaroo_Logger::log('Parse response:\n', $response);
    $response->invoicenumber = getOrderIdFromInvoiceId($response->invoicenumber, 'test');
    $order_id                =
        $response->add_order_id ?
        $response->add_order_id :
        ($response->brq_ordernumber ? $response->brq_ordernumber : $response->invoicenumber);
    Buckaroo_Logger::log(__METHOD__ . "|5|", $order_id);

    $order = new WC_Order($order_id);
    if ((int) $order_id > 0) {
        $order = new WC_Order($order_id);
        if (!isset($GLOBALS['plugin_id'])) {
            $GLOBALS['plugin_id'] = $payment_method->plugin_id . $order->get_payment_method() . "_settings";
        }
    }
    if ($response->isValid()) {
        //Check if redirect required
        $checkIfRedirectRequired = fn_process_check_redirect_required($response);
        if ($checkIfRedirectRequired){
            return $checkIfRedirectRequired;
        }

        $giftCardPartialPayment = ($response->statuscode == BuckarooAbstract::CODE_AWAITING_CONSUMER && $response->brq_transaction_type == 'I150');

        if ($response->brq_relatedtransaction_partialpayment != null || $giftCardPartialPayment) {
            Buckaroo_Logger::log('PUSH', "Partial payment PUSH received " . $response->status);
            exit();
        }
        if ($response->brq_relatedtransaction_refund != null) {
            fn_process_push_refund($order_id, $response);
        }
        Buckaroo_Logger::log('Order status: ' . $order->get_status());
        if (($response->status == BuckarooAbstract::STATUS_ON_HOLD) && ($order->get_payment_method() == 'buckaroo_paypal')) {
            $response->status = BuckarooAbstract::STATUS_CANCELED;
        }
        Buckaroo_Logger::log('Response order status: ' . $response->status);
        Buckaroo_Logger::log('Status message: ' . $response->statusmessage);

        if (!fn_process_push_meta_update($order_id, $order, $response)){
            return;
        }

        if ($response->hasSucceeded()) {
            processPushTransactionSucceeded($order_id, $order, $response, $payment_method);

        } else {
            Buckaroo_Logger::log('Payment request failed/canceled. Order status: ' . $order->get_status());
            if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled'))) {
                //We receive a valid response that the payment is canceled/failed.
                Buckaroo_Logger::log('Update status 2. Order status: failed');
                $order->update_status('failed', __($response->statusmessage, 'wc-buckaroo-bpe-gateway'));
            } else {
                Buckaroo_Logger::log('Push message. Order status cannot be changed.');
            }
            if ($response->status == BuckarooAbstract::STATUS_CANCELED) {
                Buckaroo_Logger::log('Update status 3. Order status: cancelled');
                if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled'))) {
                    $order->update_status('cancelled', __($response->statusmessage, 'wc-buckaroo-bpe-gateway'));
                } else {
                    Buckaroo_Logger::log('Push message. Order status cannot be changed.');
                }
                wc_add_notice(__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'), 'error');
            } else {
                if ($response->payment_method == 'afterpaydigiaccept' && $response->statuscode == BuckarooAbstract::CODE_REJECTED) {
                    wc_add_notice(
                        __(
                            "We are sorry to inform you that the request to pay afterwards with AfterPay is not possible at this time. This can be due to various (temporary) reasons. For questions about your rejection you can contact the customer service of AfterPay. Or you can visit the website of AfterPay and check the 'Frequently asked questions' through this <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord\" target=\"_blank\">link</a>. We advise you to choose another payment method to complete your order.",
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
        Buckaroo_Logger::log('Response not valid!');
        Buckaroo_Logger::log('Parse response:\n', $response);
        if ($response->payment_method == 'afterpaydigiaccept' && $response->statuscode == BuckarooAbstract::CODE_REJECTED) {
            wc_add_notice(
                __(
                    "We are sorry to inform you that the request to pay afterwards with AfterPay is not possible at this time. This can be due to various (temporary) reasons. For questions about your rejection you can contact the customer service of AfterPay. Or you can visit the website of AfterPay and check the 'Frequently asked questions' through this <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord\" target=\"_blank\">link</a>. We advise you to choose another payment method to complete your order.",
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

/**
 * Process response from buckaroo
 *
 * @param  WC_Payment_Gateway|null $payment_method defaults to NULL
 * @param string $response
 * @param string $mode
 * @return void|array
 */
function fn_buckaroo_process_response($payment_method = null, $response = '', $mode = '')
{
    $woocommerce = getWooCommerceObject();
    $wpdb        = getWpdbObject();
    require_once dirname(__FILE__) . '/api/paymentmethods/responsefactory.php';
    if (!session_id()) {
        @session_start();
    }
    $_SESSION['buckaroo_response'] = '';
    Buckaroo_Logger::log(" Return start / fn_buckaroo_process_response");
    Buckaroo_Logger::log("Server : " . var_export($_SERVER, true));
    if ($response == '') {
        $response = BuckarooResponseFactory::getResponse();
    }

    Buckaroo_Logger::log('Parse response:\n', $response);
    $response->invoicenumber = getOrderIdFromInvoiceId($response->invoicenumber, $mode);

    if (empty($response->brq_ordernumber)) {
        $order_id = $response->invoicenumber;
    } else {
        $order_id = $response->brq_ordernumber;
    }

    try {
        $order = new WC_Order($order_id);
        if ((int) $order_id > 0) {
            if (!isset($GLOBALS['plugin_id'])) {
                $GLOBALS['plugin_id'] = $payment_method->plugin_id . $order->get_payment_method() . "_settings";
            }
        }
    } catch (\Exception $e) {
        Buckaroo_Logger::log(__METHOD__ . "|10|");
    }

    if ($response->isValid()) {
        
        //Check if redirect required
        $checkIfRedirectRequired = fn_process_check_redirect_required($response, 'response', $payment_method, $order_id);
        if ($checkIfRedirectRequired){
            return $checkIfRedirectRequired;
        }

        Buckaroo_Logger::log(__METHOD__ . "|20|", [$order_id, $response->payment_method,$response->hasSucceeded()]);
        
        $process_response_idin = fn_process_response_idin($response, $order_id);
        if (is_array($process_response_idin)){
            return $process_response_idin;
        }
        
        Buckaroo_Logger::log('Order status: ' . $order->get_status());
        if (($response->status == BuckarooAbstract::STATUS_ON_HOLD) && ($payment_method->id == 'buckaroo_paypal')) {
            $response->status = BuckarooAbstract::STATUS_CANCELED;
        }
        Buckaroo_Logger::log('Response order status: ' . $response->status);
        Buckaroo_Logger::log('Status message: ' . $response->statusmessage);

        //Payperemail response
        if(fn_process_response_payperemail($payment_method, $response)){
            return;
        }

        if ($response->hasSucceeded()) {
            Buckaroo_Logger::log(
                'Order already in final state or  have the same status as response. Order status: ' . $order->get_status()
            );

            addSepaDirectOrderNote($response, $order);

            switch ($response->status) {
                case 'completed':
                case 'processing':
                case 'pending':
                case 'on-hold':
                    if (!is_null($payment_method)) {
                        $woocommerce->cart->empty_cart();
                        return array(
                            'result'   => 'success',
                            'redirect' => $payment_method->get_return_url($order),
                        );
                    }
                    break;
                default:
                    return;
            }
        } else {

            Buckaroo_Logger::log('Payment request failed/canceled. Order status: ' . $order->get_status());
            Buckaroo_Logger::log('||| infoLog ' . $response->status);
            if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled', 'failed', 'refund'))) {
                //We receive a valid response that the payment is canceled/failed.
                Buckaroo_Logger::log('Update status 4. Order status: failed');
                $order->update_status('failed', __($response->statusmessage, 'wc-buckaroo-bpe-gateway'));
            } else {
                Buckaroo_Logger::log('Order status cannot be changed.');
            }
            if ($response->status == BuckarooAbstract::STATUS_CANCELED) {
                Buckaroo_Logger::log('Update status 5. Order status: cancelled');
                if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled', 'failed', 'refund'))) {
                    $order->update_status('cancelled', __($response->statusmessage, 'wc-buckaroo-bpe-gateway'));
                } else {
                    Buckaroo_Logger::log('Response. Order status cannot be changed.');
                }
                wc_add_notice(__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'), 'error');
            } else {
                if (!in_array($order->get_status(), array('completed', 'processing', 'cancelled', 'failed', 'refund'))) {
                    Buckaroo_Logger::log('Update status 6. Order status: failed');
                    $order->update_status('failed', __($response->statusmessage, 'wc-buckaroo-bpe-gateway'));
                } else {
                    Buckaroo_Logger::log('Order status cannot be changed.');
                }
                if ($response->payment_method == 'afterpaydigiaccept' && $response->statuscode == BuckarooAbstract::CODE_REJECTED) {
                    wc_add_notice(
                        __(
                            "We are sorry to inform you that the request to pay afterwards with AfterPay is not possible at this time. This can be due to various (temporary) reasons. For questions about your rejection you can contact the customer service of AfterPay. Or you can visit the website of AfterPay and check the 'Frequently asked questions' through this <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord\" target=\"_blank\">link</a>. We advise you to choose another payment method to complete your order.",
                            'wc-buckaroo-bpe-gateway'
                        ),
                        'error'
                    );
                } elseif ($payment_method instanceof WC_Gateway_Buckaroo_Giftcard && $response->statuscode == BuckarooAbstract::CODE_FAILED) {
                    if ($response->statusmessage == 'Failed') {
                        wc_add_notice(
                            sprintf(
                                __('Card number or pin is incorrect for %s', 'wc-buckaroo-bpe-gateway'),
                                $response->payment_method
                            ),
                            'error'
                        );
                    } else {
                        wc_add_notice(
                            __($response->message, 'wc-buckaroo-bpe-gateway'),
                            'error'
                        );
                    }
                } elseif (($response->payment_method == "afterpay") && ($response->statuscode == BuckarooAbstract::CODE_REJECTED)) {
                    wc_add_notice(
                        __(
                            $response->ChannelError,
                            'wc-buckaroo-bpe-gateway'
                        ),
                        'error'
                    );

                } else {
                    Buckaroo_Logger::log(__METHOD__ . "|50|");
                    $error_description = 'Payment unsuccessful. Please try again or choose another payment method.';
                    wc_add_notice(__($error_description, 'wc-buckaroo-bpe-gateway'), 'error');

                    Buckaroo_Logger::log('wc session after: ' . var_export(WC()->session, true));
                    if (WooV3Plus()) {
                        if ($order->get_billing_country() == 'NL') {
                            if (strrpos($response->ChannelError, ': ') !== false) {
                                $error_description = str_replace(':', '', substr($response->ChannelError, strrpos($response->ChannelError, ': ')));
                                Buckaroo_Logger::log('||| failed status message: ' . $error_description);
                                wc_add_notice(__($error_description, 'wc-buckaroo-bpe-gateway'), 'error');
                            }
                        }
                    } else {
                        if ($order->billing_country == 'NL') {
                            if (strrpos($response->ChannelError, ': ') !== false) {
                                $error_description = str_replace(':', '', substr($response->ChannelError, strrpos($response->ChannelError, ': ')));
                                wc_add_notice(__($error_description, 'wc-buckaroo-bpe-gateway'), 'error');
                            }
                        }
                    }
                    if ($payment_method && $payment_method->get_failed_url()) {
                        Buckaroo_Logger::log(__METHOD__ . "|70|");
                        return [
                            'redirect' => $payment_method->get_failed_url() . '?bck_err=' . base64_encode($error_description)
                        ];
                    }
                }
            }
            return;
        }
    } else {
        Buckaroo_Logger::log(
            'Response not valid for order. Signature calculation failed. Order id: ' . (!empty($order_id) ? $order_id : 'order not created')
        );
        Buckaroo_Logger::log('Response not valid!');
        Buckaroo_Logger::log('Parse response:\n', $response);

        return;
    }

}

function parsePPENewTransactionId($transactions)
{
    return checkPPEtransactionsId($transactions) ? explode(',', $transactions) : '';
}

function checkPPEtransactionsId($transactions)
{
    if (!empty($transactions)) {
        return true;
    }

    return false;
}

/**
 * Split address to parts
 *
 * @param string $address
 * @return array
 */
function fn_buckaroo_get_address_components($address)
{
    $result                    = array();
    $result['house_number']    = '';
    $result['number_addition'] = '';

    $address = str_replace(array('?', '*', '[', ']', ',', '!'), ' ', $address);
    $address = preg_replace('/\s\s+/', ' ', $address);

    preg_match('/^([0-9]*)(.*?)([0-9]+)(.*)/', $address, $matches);

    if (!empty($matches[2])) {
        $result['street']          = trim($matches[1] . $matches[2]);
        $result['house_number']    = trim($matches[3]);
        $result['number_addition'] = trim($matches[4]);
    } else {
        $result['street'] = $address;
    }

    return $result;
}

/**
 * Cancel order and create new if order_awaiting_payment exists
 */
function resetOrder()
{
    $order_id = WC()->session->order_awaiting_payment;
    if ($order_id) {
        $order = wc_get_order($order_id);

        $status = get_post_status($order_id);

        if (($status == 'wc-failed' || $status == 'wc-cancelled') && wc_notice_count('error') == 0) {

            //Add generated hash to order for WooCommerce versions later than 2.5
            if (version_compare(WC()->version, '2.5', '>')) {
                $order->cart_hash = md5(json_encode(wc_clean(WC()->cart->get_cart_for_session())) . WC()->cart->total);
            }

            if (version_compare(WC()->version, '3.6', '>=')) {
                Buckaroo_Logger::log('Update status 7. Order status: cancelled');
                $order->update_status('cancelled', __($response->statusmessage ?? '', 'wc-buckaroo-bpe-gateway'));
            } else {
                $newOrder                             = wc_create_order($order);
                WC()->session->order_awaiting_payment = $newOrder->get_order_number();
            }
        }
    }
}

/**
 * Generates uniq invocie ID
 *
 * @param string $orderID
 * @param string $mode
 * @return string
 */
function getUniqInvoiceId($order_id, $mode = 'live')
{
    if (isset($_REQUEST['payment_method'])) {
        $paymentMethod = sanitize_text_field($_REQUEST['payment_method']);
    }
    $time = time();
    if (!empty($paymentMethod) && $paymentMethod == 'buckaroo_afterpay') {
        $time = substr($time, -5);
    }
    $postfix   = '';
    $invoiceId = (string) $order_id . $postfix;

    return $invoiceId;
}

/**
 * Return Order ID from previously genereated Invoice ID
 *
 * @param string $orderID
 * @param string $mode
 * @return string
 */
function getOrderIdFromInvoiceId($invoice_id, $mode = 'live')
{
    if ($mode == 'test') {
        $invoice_id = str_replace("WP_", "", $invoice_id);
    }

    return $invoice_id;
}

/**
 * Checks if WooCommerce Version 3 or greater is installed
 *
 * @return boolean
 */
function WooV3Plus()
{
    if (substr(WC()->version, 0, 1) >= 3) {
        return true;
    } else {
        return false;
    }
}

/**
 * Replaces multiple calls throughout plugin to "global $woocommerce"
 *
 * @return object
 */
function getWooCommerceObject()
{
    global $woocommerce;
    return $woocommerce;
}

/**
 * Replaces multiple calls throughout plugin to "global $wpdb"
 *
 * @return object
 */
function getWpdbObject()
{
    global $wpdb;
    return $wpdb;
}

/**
 * Write a message to the buckaroo debug log
 *
 * @param string $message, string $file, int $line
 * @return void
 */
function writeToDebug($request, $type)
{
    if (BuckarooConfig::get('BUCKAROO_DEBUG') == 'on') {
        if (!file_exists(dirname(__FILE__) . "/../traffic-debug/")) {
            mkdir(dirname(__FILE__) . "/../traffic-debug/", 0777);
        }
        $file = dirname(__FILE__) . "/../traffic-debug/" . time() . "-$type.log";
        $request->save($file);

        //Begin - Reduce sensitivity
        $content = file_get_contents($file);

        $sensative_content[] = BuckarooConfig::get('BUCKAROO_MERCHANT_KEY');
        $sensative_content[] = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $sensative_content[] = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        foreach ($sensative_content as $s) {
            $content = str_replace($s, "************", $content);
        }
        file_put_contents($file, $content);
        //End - Reduce sensitivity
    }
    return;
}

function getWCOrder($order_id)
{
    if (WooV3Plus()) {
        $order = wc_get_order($order_id);
        return $order;
    } else {
        $order = new WC_Order($order_id);
        return $order;
    }
}



function checkCurrencySupported($payment_method = '')
{
    switch ($payment_method) {
        case 'buckaroo_payperemail':
        case 'buckaroo_creditcard':
            $supported_currencies = array(
                'ARS', 'AUD', 'BRL', 'CAD', 'CHF', 'CNY',
                'CZK', 'DKK', 'EUR', 'GBP', 'HRK', 'ISK',
                'JPY', 'LTL', 'LVL', 'MXN', 'NOK', 'NZD',
                'PLN', 'RUB', 'SEK', 'TRY', 'USD', 'ZAR',
            );
            break;
        case 'buckaroo_paypal':
            $supported_currencies = array(
                'AUD', 'BRL', 'CAD', 'CHF', 'DKK', 'EUR',
                'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR',
                'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD',
                'THB', 'TRL', 'TWD', 'USD',
            );
            break;
        case 'buckaroo_transfer':
            $supported_currencies = array(
                'EUR', 'GBP', 'PLN',
            );
            break;
        case 'buckaroo_przelewy24':
            $supported_currencies = array(
                'PLN',
            );
            break;
        default:
            $supported_currencies = array('EUR');
            break;
    }
    $is_selected_currency_supported = (!in_array(get_woocommerce_currency(), $supported_currencies)) ? false : true;
    return $is_selected_currency_supported;
}

function createPayConicPage()
{
    $new_page_title    = 'Payconiq';
    $new_page_content  = '[buckaroo_payconiq]';
    $new_page_template = '';
    $page_check        = get_page_by_title($new_page_title);
    $new_page          = array(
        'post_type'    => 'page',
        'post_title'   => $new_page_title,
        'post_content' => $new_page_content,
        'post_status'  => 'publish',
        'post_author'  => 1,
    );
    if (!isset($page_check->ID)) {
        $new_page_id = wp_insert_post($new_page);
        if (!empty($new_page_template)) {
            update_post_meta($new_page_id, '_wp_page_template', $new_page_template);
        }
    }
}

function pages_with_shortcode($shortcode, $args = array())
{
    if (!shortcode_exists($shortcode)) {
        // shortcode was not registered (yet?)
        return null;
    }

    // replace get_pages with get_posts
    // if you want to search in posts
    $pages = get_pages($args);
    $list  = array();

    foreach ($pages as $page) {
        if (has_shortcode($page->post_content, $shortcode)) {
            $list[] = $page;
            break;
        }
    }

    if (count($list) == 0) {
        // Page doesn't exist. create new
        createPayConicPage();
        return pages_with_shortcode($shortcode, $args);
    }

    return $list;
}

function getCreditcardsProviders()
{
    $paymentgateways = WC_Payment_Gateways::instance();
    $creditcard      = $paymentgateways->payment_gateways()['buckaroo_creditcard'];

    return $creditcard->getCardsList();
}

function checkCreditcardProvider($creditcardProvider)
{
    $creditcardsProvidersList = getCreditcardsProviders();
    foreach ($creditcardsProvidersList as $provider) {
        if ($provider['servicename'] === $creditcardProvider) {
            return $provider;
        }
    }
    return false;
}
function getClientIpBuckaroo()
{
    $ipaddress = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    $ex = explode(",", sanitize_text_field($ipaddress));
    if (filter_var($ex[0], FILTER_VALIDATE_IP)) {
        return trim($ex[0]);
    }
    return "";
}

function roundAmount($amount) {
    if(is_scalar($amount)) {
        return round(floatval($amount), 2);
    }
    return 0;
}

function fn_process_push_refund($order_id, $response){
        //Logger

        Buckaroo_Logger::log('PUSH', "Refund payment PUSH received " . $response->status);
        $allowedPush = get_post_meta($order_id, '_pushallowed', true);
        Buckaroo_Logger::log(__METHOD__ . "|10|", $allowedPush);
        if ($response->hasSucceeded() && $allowedPush == 'ok') {
            $tmp = get_post_meta($order_id, '_refundbuckaroo' . $response->transactions, true);
            if (empty($tmp)) {
                add_post_meta($order_id, '_refundbuckaroo' . $response->transactions, 'ok', true);
                $refund = wc_create_refund(
                    array(
                        'amount'     => $response->amount_credit,
                        'reason'     => 'Push automatic refund from BPE; Please restock items manually',
                        'order_id'   => $order_id,
                        'line_items' => array(),
                    )
                );
            }

        }
        exit();
}

function fn_process_push_meta_update($order_id, $order, $response){

    if (strtolower($order->get_payment_method()) === 'buckaroo_payperemail') {
        $transactionsArray = parsePPENewTransactionId($response->transactions);
        if (!empty($transactionsArray) && $response->statuscode == BuckarooAbstract::CODE_SUCCESS) {
            $creditcardProvider = checkCreditcardProvider($response->payment_method);
            update_post_meta($order_id, '_transaction_id', $transactionsArray[count($transactionsArray) - 1]);
            if ($creditcardProvider) {
                update_post_meta($order_id, '_payment_method', 'buckaroo_creditcard');
                update_post_meta($order_id, '_payment_method_title', 'Creditcards');
                update_post_meta($order_id, '_payment_method_transaction', $response->payment_method);
                update_post_meta($order_id, '_wc_order_payment_issuer', $response->payment_method);
            } else {
                update_post_meta($order_id, '_payment_method', 'buckaroo_' . strtolower($response->payment_method));
                $responsePaymentMethod = $response->payment_method !== 'payperemail' ? ' + ' . $response->payment_method : '';
                update_post_meta($order_id, '_payment_method_title', 'PayperEmail' . $responsePaymentMethod);
                update_post_meta($order_id, '_payment_method_transaction', $response->payment_method);
            }
        }
    } elseif (strtolower($order->get_payment_method()) === 'buckaroo_sepadirectdebit' && $response->payment_method === 'payperemail') {
        return false;
    }
    return true;
}

function processPushTransactionSucceeded($order_id, $order, $response, $payment_method){

    $woocommerce = getWooCommerceObject();
    $wpdb        = getWpdbObject();

    if (!session_id()) {
        @session_start();
    }

    //Logger

    if (in_array($order->get_status(), array('completed', 'processing'))) {
        Buckaroo_Logger::log(
            'Push message. Order already in final state or have the same status as response. Order status: ' . $order->get_status()
        );
        switch ($response->status) {
            case 'completed':
                if (!is_null($payment_method)) {
                    return array(
                        'result'   => 'success',
                        'redirect' => $payment_method->get_return_url($order),
                    );
                }
                break;
            default:
                return;
        }
    } else {
        switch ($response->status) {
            case 'completed':
                $transaction        = $response->transactions;
                $payment_methodname = $response->payment_method;
                if ($response->brq_relatedtransaction_partialpayment != null) {
                    $transaction        = $response->brq_relatedtransaction_partialpayment;
                    $payment_methodname = 'grouptransaction';
                }

                if ((int) $order_id > 0) {
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
                $clean_order_no = (int) str_replace('#', '', $order_id);
                add_post_meta($clean_order_no, '_payment_method_transaction', $payment_methodname, true);

                // Calc total received amount
                $prefix     = "buckaroo_settlement_";
                $settlement = $prefix . $response->payment;

                $orderAmount            = (float) $order->get_total();
                $paidAmount             = (float) $response->amount;
                $alreadyPaidSettlements = 0;
                $isNewPayment           = true;
                if ($items = get_post_meta($order_id)) {
                    foreach ($items as $key => $meta) {
                        if (strstr($key, $prefix) !== false && strstr($key, $response->payment) === false) {
                            $alreadyPaidSettlements += (float)$meta[0];
                        }

                        // check if push is a new payment
                        if (strstr($key, $prefix) !== false && strstr($key, $response->payment) !== false) {
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
                Buckaroo_Logger::log('Update status 1. Order status: on-hold');
                $order->update_status('on-hold', __($response->statusmessage, 'wc-buckaroo-bpe-gateway'));
                // Reduce stock levels
                break;
        }
        // Remove cart
        $woocommerce->cart->empty_cart();
        if (isset($response->consumerMessage['HtmlText'])) {
            $_SESSION['buckaroo_response'] = $response->consumerMessage['HtmlText'];
        }
        // Return thank you page redirect
        if (!is_null($payment_method)) {
            return array(
                'result'   => 'success',
                'redirect' => $payment_method->get_return_url($order),
            );
        }

    } 

}

function fn_process_response_payperemail($payment_method, $response){
    if ($payment_method->id == 'buckaroo_payperemail') {
        Buckaroo_Logger::log(__METHOD__, "Process paypermail");
        if (is_admin()) {
            if ($response->hasSucceeded()) {
                if (!isset($response->getResponse()->ConsumerMessage)) {
                    $buckaroo_admin_notice = array(
                        'type'    => 'success',
                        'message' => 'Your paylink: <a target="_blank" href="' . $response->getPayLink() . '">' . $response->getPayLink() . '</a>',
                    );
                }
            } else {
                $parameterError = '';
                if (isset($response->getResponse()->RequestErrors->ParameterError)) {
                    $parameterErrorArray = $response->getResponse()->RequestErrors->ParameterError;
                    if (is_array($parameterErrorArray)) {
                        foreach ($parameterErrorArray as $key => $value) {
                            $parameterError .= '<br/>' . $value->_;
                        }
                    }
                }
                $buckaroo_admin_notice = array(
                    'type'    => 'error',
                    'message' => $response->statusmessage . ' ' . $parameterError,
                );
            }
             Buckaroo_Logger::log(__METHOD__."|10|", $parameterError);

            set_transient(get_current_user_id() . 'buckarooAdminNotice', $buckaroo_admin_notice);
            return true;
        }
    }
}

function addSepaDirectOrderNote($response, $order){
    if ($response->payment_method == 'SepaDirectDebit') {
        /* @var $response Response */
        foreach ($response->getResponse()->Services->Service->ResponseParameter as $param) {
            if ($param->Name == 'MandateReference') {
                $order->add_order_note('MandateReference: ' . $param->_, 1);
            }
            if ($param->Name == 'MandateDate') {
                $order->add_order_note('MandateDate: ' . $param->_, 1);
            }
        }
    }
}

function fn_process_response_idin($response, $order_id = null){
    if (!$order_id && ($response->payment_method == 'IDIN') && !$response->hasSucceeded()) {
        Buckaroo_Logger::log(__METHOD__ . "|25|");
        $message = '';
        if (isset($response->getResponse()->Status->SubCode->_)) {
            $message = $response->getResponse()->Status->SubCode->_;
        }
        Buckaroo_Logger::log(__METHOD__ . "|30|", $message);

        return array(
            'result'   => 'error',
            'message' => $message
        );
    }else{
        return false;
    }
}

function fn_process_check_redirect_required($response, $mode = null, $payment_method = null, $order_id = null){
    if ($response->isRedirectRequired()) {
        if ($payment_method->id == 'buckaroo_payconiq' && $mode == 'response' && !empty($order_id)) {
            $key           = $response->transactionId;
            $invoiceNumber = $response->invoicenumber;
            $amount        = $response->amount;
            $currency      = get_woocommerce_currency();
            return array(
                'result'   => 'success',
                'redirect' => home_url('/') . 'payconiqQrcode?' .
                "transactionKey=" . $key .
                "&invoicenumber=" . $invoiceNumber .
                "&amount=" . $amount .
                "&returnUrl=" . $payment_method->notify_url .
                "&order_id=" . (int) $order_id .
                "&currency=" . $currency,
            );
        } else {
            return array(
                'result'   => 'success',
                'redirect' => $response->getRedirectUrl(),
            );
        }
    }
    return false;
}

/**
 * Convert $_POST json string to array and sanitize it  
 *
 * @param string $key
 *
 * @return array
 */
function buckaroo_request_sanitized_json($key)
{
    if (!isset( $_POST[$key] ) || !is_string( $_POST[$key] )) {
        return array();
    }

    $result = json_decode( wp_unslash( $_POST[$key]  ), true );
    if (!is_array($result)) {
        return array();
    }

    return map_deep(
        $result,
        'sanitize_text_field'
    );
}