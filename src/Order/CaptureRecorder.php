<?php

namespace Buckaroo\Woocommerce\Order;

use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use Buckaroo\Woocommerce\Services\NamedLock;
use RuntimeException;
use Throwable;
use WC_Order;

class CaptureRecorder
{
    private const LOCK_WAIT_SECONDS = 5;

    public static function record(
        WC_Order $order,
        $captureAmount,
        string $currency,
        CaptureAllocation $allocation,
        string $transactionKey,
        array $responseData = [],
        array $products = []
    ): CaptureResult {
        $lockKey = 'order';
        if (! NamedLock::acquire('capture_record', $order, $lockKey, self::LOCK_WAIT_SECONDS)) {
            return CaptureResult::unknown(
                __('Capture succeeded but could not be recorded locally; reconciliation is required.', 'wc-buckaroo-bpe-gateway'),
                $transactionKey
            );
        }

        try {
            $order->read_meta_data(true);
            if (OrderMeta::get($order, '_capturebuckaroo' . $transactionKey)) {
                return CaptureResult::succeeded($responseData, $transactionKey);
            }

            global $wpdb;
            if ($wpdb->query('START TRANSACTION') === false) {
                throw new RuntimeException(__('Could not start the local capture transaction.', 'wc-buckaroo-bpe-gateway'));
            }

            try {
                $captureAmount = number_format((float) $captureAmount, 2, '.', '');
                $capturedTotal = (float) $captureAmount;
                $wasCaptured = (bool) OrderMeta::get($order, '_wc_order_is_captured');
                if ($wasCaptured) {
                    $capturedTotal += (float) OrderMeta::get($order, '_wc_order_amount_captured');
                }

                $ledger = $allocation->toLedger();
                $order->update_meta_data('_wc_order_is_captured', true);
                $order->update_meta_data(
                    '_wc_order_amount_captured',
                    $wasCaptured ? $capturedTotal : $captureAmount
                );
                $order->add_meta_data('_wc_order_captures', [
                    'currency' => $currency,
                    'id' => $order->get_id() . substr(hash('sha256', $transactionKey), 0, 8),
                    'amount' => $captureAmount,
                    'line_item_qtys' => $ledger['line_item_qtys'],
                    'line_item_totals' => $ledger['line_item_totals'],
                    'line_item_tax_totals' => $ledger['line_item_tax_totals'],
                    'transaction_id' => $transactionKey,
                ]);
                $order->add_meta_data('_capturebuckaroo' . $transactionKey, 'ok', true);
                $order->update_meta_data('_pushallowed', 'ok');

                if (! empty($products)) {
                    $order->add_meta_data('buckaroo_capture', wp_json_encode([
                        'OriginalTransactionKey' => $transactionKey,
                        'products' => $products,
                    ]));
                }

                $wpdb->last_error = '';
                $order->save_meta_data();
                $order->read_meta_data(true);

                if (
                    ! self::isRecorded(
                        $order,
                        $captureAmount,
                        $capturedTotal,
                        $currency,
                        $transactionKey,
                        $ledger,
                        $products
                    )
                ) {
                    throw new RuntimeException(__('Could not verify the local capture record.', 'wc-buckaroo-bpe-gateway'));
                }

                $noteId = $order->add_order_note(
                    sprintf(
                        __('Captured %1$s - Capture transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                        $captureAmount . ' ' . $currency,
                        $transactionKey
                    )
                );
                if (! $noteId || $wpdb->last_error !== '') {
                    throw new RuntimeException(__('Could not record the capture order note.', 'wc-buckaroo-bpe-gateway'));
                }

                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException(__('Could not commit the local capture record.', 'wc-buckaroo-bpe-gateway'));
                }
            } catch (Throwable $exception) {
                $wpdb->query('ROLLBACK');
                $order->read_meta_data(true);
                throw $exception;
            }

            return CaptureResult::succeeded($responseData, $transactionKey);
        } finally {
            NamedLock::release('capture_record', $order, $lockKey);
        }
    }

    private static function isRecorded(
        WC_Order $order,
        string $captureAmount,
        float $capturedTotal,
        string $currency,
        string $transactionKey,
        array $ledger,
        array $products
    ): bool {
        global $wpdb;

        $recordedCapture = false;
        foreach (OrderMeta::get($order, '_wc_order_captures', false) as $capture) {
            if (
                is_array($capture) &&
                ($capture['transaction_id'] ?? '') === $transactionKey &&
                ($capture['currency'] ?? '') === $currency &&
                abs((float) ($capture['amount'] ?? 0) - (float) $captureAmount) < 0.01 &&
                ($capture['line_item_qtys'] ?? '') === $ledger['line_item_qtys'] &&
                ($capture['line_item_totals'] ?? '') === $ledger['line_item_totals'] &&
                ($capture['line_item_tax_totals'] ?? '') === $ledger['line_item_tax_totals']
            ) {
                $recordedCapture = true;
                break;
            }
        }

        $productsRecorded = empty($products);
        foreach (OrderMeta::get($order, 'buckaroo_capture', false) as $captureProducts) {
            $captureProducts = is_string($captureProducts)
                ? json_decode($captureProducts, true)
                : null;
            if (($captureProducts['OriginalTransactionKey'] ?? '') === $transactionKey) {
                $productsRecorded = true;
                break;
            }
        }

        return $wpdb->last_error === '' &&
            (bool) OrderMeta::get($order, '_capturebuckaroo' . $transactionKey) &&
            (bool) OrderMeta::get($order, '_wc_order_is_captured') &&
            OrderMeta::get($order, '_pushallowed') === 'ok' &&
            abs((float) OrderMeta::get($order, '_wc_order_amount_captured') - $capturedTotal) < 0.01 &&
            $recordedCapture &&
            $productsRecorded;
    }
}
