<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use Buckaroo\Woocommerce\Services\Logger;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use WP_Error;

class CaptureAction
{
    public function handle(TransactionResponse $response, $order, $currency, $products = null)
    {
        if (! isset($_POST['capture_amount']) || ! is_scalar($_POST['capture_amount'])) {
            return false;
        }

        $capture_amount = sanitize_text_field($_POST['capture_amount']);
        if ($response && $response->isSuccess()) {
            // SET the flags
            // check if order has already been captured
            if (get_post_meta($order->get_id(), '_wc_order_is_captured', true)) {
                // Order already captured
                // Add the other values of the capture so we have the full value captured
                $previousCaptures = (float) get_post_meta($order->get_id(), '_wc_order_amount_captured', true);
                $total = $previousCaptures + (float) $capture_amount;
                update_post_meta($order->get_id(), '_wc_order_amount_captured', $total);
            } else {
                // Order not captured yet
                // Set first amout_captured and is_captured flag
                update_post_meta($order->get_id(), '_wc_order_is_captured', true);
                update_post_meta($order->get_id(), '_wc_order_amount_captured', $capture_amount);
            }

            $str = '';
            $characters = range('0', '9');
            $max = count($characters) - 1;
            for ($i = 0; $i < 2; $i++) {
                $rand = mt_rand(0, $max);
                $str .= $characters[$rand];
            }

            // Set the flag that contains all the items and taxes that have been captured
            add_post_meta(
                $order->get_id(),
                '_wc_order_captures',
                [
                    'currency' => $currency,
                    'id' => $order->get_id() . $str,
                    'amount' => $capture_amount,
                    'line_item_qtys' => isset($_POST['line_item_qtys']) ? sanitize_text_field(wp_unslash($_POST['line_item_qtys']), true) : '',
                    'line_item_totals' => isset($_POST['line_item_totals']) ? sanitize_text_field(wp_unslash($_POST['line_item_totals']), true) : '',
                    'line_item_tax_totals' => isset($_POST['line_item_tax_totals']) ? sanitize_text_field(wp_unslash($_POST['line_item_tax_totals']), true) : '',
                    'transaction_id' => $response->getTransactionKey(),
                ]
            );

            add_post_meta($order->get_id(), '_capturebuckaroo' . $response->getTransactionKey(), 'ok', true);
            update_post_meta($order->get_id(), '_pushallowed', 'ok');

            $order->add_order_note(
                sprintf(
                    __('Captured %1$s - Capture transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                    $capture_amount . ' ' . $currency,
                    $response->getTransactionKey()
                )
            );

            // Store the transaction_key together with captured products, we need this for refunding
            if ($products != null) {
                $capture_data = json_encode(
                    [
                        'OriginalTransactionKey' => $response->getTransactionKey(),
                        'products' => $products,
                    ]
                );
                add_post_meta($order->get_id(), 'buckaroo_capture', $capture_data, false);
            }
            wp_send_json_success($response->toArray());
        }
        if (! empty($response->hasSomeError())) {
            Logger::log(__METHOD__, $response->getSomeError());
            $order->add_order_note(
                sprintf(
                    __(
                        'Capture failed for transaction ID: %s ' . "\n" . $response->getSomeError(),
                        'wc-buckaroo-bpe-gateway'
                    ),
                    $order->get_transaction_id()
                )
            );
            update_post_meta($order->get_id(), '_pushallowed', 'ok');

            return new WP_Error('error_capture', __('Capture failed: ') . $response->getSomeError());
        } else {
            $order->add_order_note(
                sprintf(
                    __('Capture failed for transaction ID: %s', 'wc-buckaroo-bpe-gateway'),
                    $order->get_transaction_id()
                )
            );
            update_post_meta($order->get_id(), '_pushallowed', 'ok');

            return false;
        }
    }
}
