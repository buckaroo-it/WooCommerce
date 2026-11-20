<?php

namespace Buckaroo\Woocommerce\Order;

use Buckaroo\Woocommerce\PaymentProcessors\Actions\RefundAction;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\NamedLock;
use Buckaroo\Woocommerce\Services\Request;
use WC_Order;
use WC_Order_Refund;
use WP_Error;

/**
 * Core class for order refund
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 3.3.0
 *
 * @link      https://www.buckaroo.eu/
 */
class OrderCaptureRefund
{
    public function __construct()
    {
        add_action('wp_ajax_bl_refund_capture', [$this, 'refund_capture']);
    }

    /**
     * Refund a capture
     *
     * @return void
     */
    public function refund_capture()
    {
        if (! check_ajax_referer('order-item', 'security', false)) {
            wp_send_json(
                [
                    'error' => __('Invalid security token. Please reload the page and try again.', 'wc-buckaroo-bpe-gateway'),
                ]
            );
        }

        if (! current_user_can('edit_shop_orders')) {
            wp_send_json(
                [
                    'error' => __('You are not allowed to refund this order.', 'wc-buckaroo-bpe-gateway'),
                ]
            );
        }

        $request = new Request();
        if ($request->input('order_id') === null) {
            wp_send_json(
                [
                    'error' => __('A order id is required', 'wc-buckaroo-bpe-gateway'),
                ]
            );
        }
        if ($request->input('capture_id') === null) {
            wp_send_json(
                [
                    'error' => __('A capture id is required', 'wc-buckaroo-bpe-gateway'),
                ]
            );
        }

        $order_id = absint($request->input('order_id'));
        $order = $order_id > 0 ? wc_get_order($order_id) : false;
        if (! $order instanceof WC_Order) {
            wp_send_json(
                [
                    'error' => __('A order id is required', 'wc-buckaroo-bpe-gateway'),
                ]
            );
        }

        if (! Helper::currentUserCanEditOrder($order)) {
            wp_send_json(
                [
                    'error' => __('You are not allowed to refund this order.', 'wc-buckaroo-bpe-gateway'),
                ]
            );
        }

        $capture_id = $request->input('capture_id');
        $capture = is_scalar($capture_id)
            ? $this->get_capture_transaction_by_id($order_id, (string) $capture_id)
            : null;

        $result = $capture !== null
            ? $this->refund_capture_in_woocommerce($request, $order, $capture)
            : new WP_Error('error_refund', __('Cannot process refund', 'wc-buckaroo-bpe-gateway'));

        if ($result instanceof WP_Error) {
            wp_send_json(
                [
                    'error' => $result->get_error_message(),
                ]
            );
        }

        $this->set_refunded_capture($order_id, (string) $capture_id);
        wp_send_json(['success' => true]);
    }

    /**
     * Refund what is left of a capture. WooCommerce creates the refund first
     * and calls the gateway (refund_payment); it deletes the refund again when
     * the gateway refund fails, and restocks only after it succeeded. The
     * gateway refund is sent against this capture. A capture that was
     * partially refunded before is refunded by amount only.
     *
     * @return WC_Order_Refund|WP_Error
     */
    protected function refund_capture_in_woocommerce(Request $request, WC_Order $order, array $capture)
    {
        $captureKey = $capture['transaction_id'] ?? null;
        if (! Helper::isValidTransactionKey($captureKey)) {
            return new WP_Error('error_refund', __('Cannot process refund', 'wc-buckaroo-bpe-gateway'));
        }

        $capturedCents = RefundLedger::toCents($capture['amount'] ?? null);
        $remainingCents = RefundLedger::remainingCaptureCents($order, $captureKey);
        if ($capturedCents === null || $remainingCents === null || $remainingCents <= 0) {
            return new WP_Error('error_refund', __('This capture has already been refunded.', 'wc-buckaroo-bpe-gateway'));
        }

        $line_items = [];
        if ($remainingCents === $capturedCents) {
            $line_items = $this->get_refund_items(new CaptureTransaction($capture, $order));
        }

        $refund = RefundAction::sendAgainst(
            $captureKey,
            function () use ($request, $order, $remainingCents, $line_items) {
                return wc_create_refund(
                    [
                        'amount' => RefundLedger::formatCents($remainingCents),
                        'reason' => sanitize_text_field((string) ($request->input('reason') ?? '')),
                        'order_id' => $order->get_id(),
                        'line_items' => $line_items,
                        'refund_payment' => true,
                        'restock_items' => ! empty($line_items) && $request->input('restock') == 'true',
                    ]
                );
            }
        );

        if ($refund instanceof WP_Error || $refund instanceof WC_Order_Refund) {
            return $refund;
        }

        return new WP_Error('error_refund', __('Cannot process refund', 'wc-buckaroo-bpe-gateway'));
    }

    /**
     * Get a stored capture by its id
     *
     *
     * @return array|null
     */
    protected function get_capture_transaction_by_id(int $order_id, string $id)
    {
        $captures = OrderMeta::get($order_id, '_wc_order_captures', false);
        foreach ($captures as $capture) {
            if ($capture['id'] == $id) {
                return $capture;
            }
        }
    }

    protected function get_refund_items(CaptureTransaction $capture_transaction)
    {
        $items_for_refund = [
            'qty' => [],
            'refund_total' => [],
            'refund_tax' => [],
        ];
        foreach ($capture_transaction->get_items() as $item) {
            $qty = $capture_transaction->get_qty($item->get_line_item_id());
            $items_for_refund['qty'][$item->get_line_item_id()] = $qty;
            $items_for_refund['refund_total'][$item->get_line_item_id()] = $this->get_refund_item_total($item, $qty);
            $items_for_refund['refund_tax'][$item->get_line_item_id()] = $this->get_refund_item_tax_total($item, $qty);
        }

        return $items_for_refund;
    }

    protected function get_refund_item_total(OrderItem $item, $qty)
    {
        return $item->get_unit_price(false) * $qty;
    }

    public function get_refund_item_tax_total(OrderItem $item, $qty)
    {
        $item_tax_total = [];

        $taxes = $item->get_taxes();

        if (isset($taxes['total'])) {
            foreach ($taxes['total'] as $tax_id => $tax) {
                $item_tax_total[$tax_id] = $tax / $item->get_quantity() * $qty;
            }
        }

        return $item_tax_total;
    }

    public function set_refunded_capture(int $order_id, string $capture_id)
    {
        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            return false;
        }

        if (! NamedLock::acquire(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $order, PushProcessor::SETTLEMENT_LOCK_KEY, PushProcessor::SETTLEMENT_LOCK_WAIT_SECONDS)) {
            return false;
        }

        try {
            $order->read_meta_data(true);
            $refunded_captures = $this->get_refunded_captures($order_id);
            if (in_array($capture_id, array_map('strval', $refunded_captures), true)) {
                return true;
            }
            array_push($refunded_captures, $capture_id);

            return OrderMeta::update(
                $order,
                'buckaroo_captures_refunded',
                json_encode($refunded_captures)
            );
        } finally {
            NamedLock::release(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $order, PushProcessor::SETTLEMENT_LOCK_KEY);
        }
    }

    /**
     * Get refunded captures for $order_id
     *
     *
     * @return array
     */
    protected function get_refunded_captures(int $order_id)
    {
        $refunded_captures = OrderMeta::get($order_id, 'buckaroo_captures_refunded');
        if (is_string($refunded_captures)) {
            $refunded_captures_decoded = json_decode($refunded_captures);
            if (is_array($refunded_captures_decoded)) {
                return $refunded_captures_decoded;
            }
        }

        return [];
    }
}
