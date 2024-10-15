<?php

use Buckaroo\Resources\Constants\ResponseStatus;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\Logger;


function buckaroo_handle_unsuccessful_payment($status_code)
{
    return in_array($status_code, [ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_USER, ResponseStatus::BUCKAROO_STATUSCODE_REJECTED]);
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
                Logger::log('Update status 7. Order status: cancelled');
                $order->update_status('cancelled');
            } else {
                $newOrder = wc_create_order($order);
                WC()->session->order_awaiting_payment = $newOrder->get_id();
            }
        }
    }
}


/**
 * Checks if WooCommerce Version 3 or greater is installed
 *
 * @return boolean
 */
function WooV3Plus()
{
    return substr(WC()->version, 0, 1) >= 3;
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

function getCreditcardsProviders()
{
    $paymentgateways = WC_Payment_Gateways::instance();
    $creditcard = $paymentgateways->payment_gateways()['buckaroo_creditcard'];

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

function roundAmount($amount)
{
    if (is_scalar($amount) && is_numeric($amount)) {
        return (float)number_format($amount, 2, '.', '');
    }
    return 0;
}




function fn_process_check_redirect_required(ResponseParser $responseParser)
{
    if ($responseParser->hasRedirect()) {
        return array(
            'result' => 'success',
            'redirect' => $responseParser->getRedirectUrl(),
        );
    }
    return false;
}
