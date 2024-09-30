<?php

namespace WC_Buckaroo\WooCommerce\Capture;

use Buckaroo_Http_Request;
use WC_Buckaroo\WooCommerce\Payment\Buckaroo_Order_Details;
use WC_Buckaroo\WooCommerce\SDK\Buckaroo_Sdk_Response;
use WC_Order;

class Buckaroo_Capture_Processor
{
    private Buckaroo_Order_Details $order_details;

    private Buckaroo_Http_Request $request;

    public function __construct(Buckaroo_Http_Request $request)
    {
        $this->request = $request;
    }

    public function process(
        Buckaroo_Sdk_Response $response
    )
    {
        $order_id = $response->get_order_id() ?? $this->request->request('order_id');
        if ($order_id === null) {
            return $this->failed(__('Could not process payment', 'wc-buckaroo-bpe-gateway'));
        }

        $this->init(intval($order_id));

        if (
            $response->is_success()
        ) {
            $this->save_capture($response);
            return [
                'success' => true,
            ];
        }

        return $this->failed($response->get_some_error());
    }

    private function init(int $order_id)
    {
        $order = new WC_Order($order_id);
        $this->order_details = new Buckaroo_Order_Details($order);
    }

    private function save_capture(Buckaroo_Sdk_Response $response)
    {
        $capture_amount = $response->get_captured_amount();
        $order = $this->order_details->get_order();
        $order_id = $order->get_id();
        $currency = $order->get_currency();
        // SET the flags
        // check if order has already been captured
        if (get_post_meta($order_id, '_wc_order_is_captured', true)) {

            // Order already captured
            // Add the other values of the capture so we have the full value captured
            $previousCaptures = (float)get_post_meta($order_id, '_wc_order_amount_captured', true);
            $total = $previousCaptures + (float)$capture_amount;
            update_post_meta($order_id, '_wc_order_amount_captured', $total);
        } else {

            // Order not captured yet
            // Set first amout_captured and is_captured flag
            update_post_meta($order_id, '_wc_order_is_captured', true);
            update_post_meta($order_id, '_wc_order_amount_captured', $capture_amount);
        }

        // Set the flag that contains all the items and taxes that have been captured
        add_post_meta($order_id, '_wc_order_captures', array(
            'currency' => $currency,
            'id' => $order_id . "-" . $response->get_transaction_key(),
            'amount' => $capture_amount,
            'line_item_qtys' => $this->request->request('line_item_qtys') ?? '',
            'line_item_totals' => $this->request->request('line_item_totals') ?? '',
            'line_item_tax_totals' => $this->request->request('line_item_tax_totals') ?? '',
            'transaction_id' => $response->get_transaction_key()
        ));

        add_post_meta($order_id, '_capturebuckaroo' . $response->get_transaction_key(), 'ok', true);
        update_post_meta($order_id, '_pushallowed', 'ok');

        $order->add_order_note(
            sprintf(
                __('Captured %1$s - Capture transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                $capture_amount . ' ' . $currency,
                $response->get_transaction_key()
            )
        );
    }


    /**
     * Redirect back to checkout with message
     *
     * @param string $message
     *
     * @return array
     */
    private function failed(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
        ];
    }
}
