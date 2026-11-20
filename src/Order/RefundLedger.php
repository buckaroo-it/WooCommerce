<?php

namespace Buckaroo\Woocommerce\Order;

use WC_Order;

/**
 * Buckaroo refunds of an order, per original transaction (a capture, or the
 * payment when the order has no captures).
 *
 * Callers hold the settlement lock (PushProcessor::SETTLEMENT_LOCK_PURPOSE)
 * and refresh the order meta before they read or write the ledger.
 *
 * Entry states:
 * - reserved:  the refund is being sent, or its outcome is not known yet
 * - pending:   Buckaroo accepted the refund, it is not final yet
 * - completed: Buckaroo confirmed the refund
 * - failed:    Buckaroo reported the refund as failed after accepting it
 *
 * Amounts are stored in cents.
 */
class RefundLedger
{
    public const META_KEY = '_buckaroo_refund_ledger';

    public const STATE_RESERVED = 'reserved';

    public const STATE_PENDING = 'pending';

    public const STATE_COMPLETED = 'completed';

    public const STATE_FAILED = 'failed';

    /** A reservation whose outcome is unknown stops counting after this time. */
    public const RESERVATION_TTL = DAY_IN_SECONDS;

    /**
     * Amount in cents, or null when it is not a valid non-negative amount.
     *
     * @param  mixed  $amount
     */
    public static function toCents($amount): ?int
    {
        if (! is_scalar($amount) || ! is_numeric($amount) || ! is_finite((float) $amount) || (float) $amount < 0) {
            return null;
        }

        return (int) round((float) $amount * 100);
    }

    public static function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function entries(WC_Order $order): array
    {
        $entries = OrderMeta::get($order, self::META_KEY);

        return is_array($entries) ? $entries : [];
    }

    /**
     * Captured cents per capture transaction key, in capture order.
     *
     * @return array<string, array{id: string, cents: int}>
     */
    public static function captures(WC_Order $order): array
    {
        $captures = [];
        foreach (OrderMeta::get($order, '_wc_order_captures', false) as $capture) {
            if (! is_array($capture) || empty($capture['transaction_id']) || ! is_string($capture['transaction_id'])) {
                continue;
            }

            $cents = self::toCents($capture['amount'] ?? null);
            if ($cents === null) {
                continue;
            }

            $key = $capture['transaction_id'];
            if (! isset($captures[$key])) {
                $captures[$key] = ['id' => (string) ($capture['id'] ?? ''), 'cents' => 0];
            }
            $captures[$key]['cents'] += $cents;
        }

        return $captures;
    }

    /**
     * Cents refunded or still in progress against an original transaction.
     */
    public static function refundedCents(WC_Order $order, string $parentKey): int
    {
        $cents = 0;
        foreach (self::entries($order) as $entry) {
            if (($entry['parent'] ?? null) === $parentKey && self::counts($entry)) {
                $cents += (int) ($entry['cents'] ?? 0);
            }
        }

        return $cents;
    }

    /**
     * Cents that can still be refunded against a capture. A capture marked as
     * refunded by an earlier plugin version has nothing left.
     */
    public static function remainingCaptureCents(WC_Order $order, string $captureKey): ?int
    {
        $captures = self::captures($order);
        if (! isset($captures[$captureKey])) {
            return null;
        }

        if (in_array($captures[$captureKey]['id'], self::legacyRefundedCaptureIds($order), true)) {
            return 0;
        }

        return max(0, $captures[$captureKey]['cents'] - self::refundedCents($order, $captureKey));
    }

    /**
     * The original transaction a refund of this amount is sent against, or
     * null when no single transaction can take the whole amount.
     *
     * With captures, the refund goes to one capture with enough remaining
     * amount: the preferred one when given, otherwise the latest. Without
     * captures it goes to the payment, within the order total.
     */
    public static function selectParent(WC_Order $order, int $cents, ?string $preferredKey = null): ?string
    {
        if ($cents <= 0) {
            return null;
        }

        $captures = self::captures($order);
        if (! empty($captures)) {
            $candidates = $preferredKey !== null && $preferredKey !== ''
                ? [$preferredKey]
                : array_reverse(array_keys($captures));

            foreach ($candidates as $captureKey) {
                $remaining = self::remainingCaptureCents($order, (string) $captureKey);
                if ($remaining !== null && $remaining >= $cents) {
                    return (string) $captureKey;
                }
            }

            return null;
        }

        $paymentKey = $preferredKey !== null && $preferredKey !== ''
            ? $preferredKey
            : (string) $order->get_transaction_id('edit');
        $orderCents = self::toCents($order->get_total('edit'));
        if ($orderCents === null || self::refundedCents($order, $paymentKey) + $cents > $orderCents) {
            return null;
        }

        return $paymentKey;
    }

    /**
     * Whether a transaction key is a payment or capture of this order.
     */
    public static function isOrderTransaction(WC_Order $order, string $key): bool
    {
        if ($key === '') {
            return false;
        }

        if ($key === (string) $order->get_transaction_id('edit')) {
            return true;
        }

        if (isset(self::captures($order)[$key])) {
            return true;
        }

        $settlements = OrderMeta::get($order, 'buckaroo_settlement');
        if (is_array($settlements) && array_key_exists($key, $settlements)) {
            return true;
        }

        return (bool) OrderMeta::get($order, '_capturebuckaroo' . $key);
    }

    /**
     * @return string The entry id
     */
    public static function reserve(WC_Order $order, string $parentKey, int $cents, ?int $wcRefundId = null): string
    {
        $id = wp_generate_uuid4();
        $entries = self::entries($order);
        $entries[$id] = [
            'parent' => $parentKey,
            'cents' => $cents,
            'state' => self::STATE_RESERVED,
            'refund_key' => null,
            'wc_refund_id' => $wcRefundId,
            'created' => time(),
        ];
        self::save($order, $entries);

        return $id;
    }

    /**
     * Record a refund Buckaroo reported through a push, which no admin
     * refund of this site started.
     *
     * @return string The entry id
     */
    public static function addCompleted(WC_Order $order, string $parentKey, int $cents, string $refundKey): string
    {
        $id = self::reserve($order, $parentKey, $cents);
        self::update($order, $id, ['state' => self::STATE_COMPLETED, 'refund_key' => $refundKey]);

        return $id;
    }

    public static function update(WC_Order $order, string $id, array $changes): void
    {
        $entries = self::entries($order);
        if (! isset($entries[$id])) {
            return;
        }

        $entries[$id] = array_merge($entries[$id], $changes);
        self::save($order, $entries);
    }

    public static function remove(WC_Order $order, string $id): void
    {
        $entries = self::entries($order);
        if (! isset($entries[$id])) {
            return;
        }

        unset($entries[$id]);
        self::save($order, $entries);
    }

    public static function get(WC_Order $order, string $id): ?array
    {
        $entries = self::entries($order);

        return $entries[$id] ?? null;
    }

    public static function findByRefundKey(WC_Order $order, string $refundKey): ?string
    {
        foreach (self::entries($order) as $id => $entry) {
            if (($entry['refund_key'] ?? null) === $refundKey) {
                return (string) $id;
            }
        }

        return null;
    }

    /**
     * The oldest reservation without a Buckaroo key for this transaction and
     * amount: the refund a push reports when the admin request ended
     * without a usable response.
     */
    public static function findReservation(WC_Order $order, string $parentKey, int $cents): ?string
    {
        foreach (self::entries($order) as $id => $entry) {
            if (
                ($entry['state'] ?? null) === self::STATE_RESERVED &&
                empty($entry['refund_key']) &&
                ($entry['parent'] ?? null) === $parentKey &&
                (int) ($entry['cents'] ?? -1) === $cents
            ) {
                return (string) $id;
            }
        }

        return null;
    }

    private static function counts(array $entry): bool
    {
        $state = $entry['state'] ?? null;
        if ($state === self::STATE_COMPLETED || $state === self::STATE_PENDING) {
            return true;
        }

        return $state === self::STATE_RESERVED &&
            (int) ($entry['created'] ?? 0) + self::RESERVATION_TTL > time();
    }

    /**
     * @return string[]
     */
    private static function legacyRefundedCaptureIds(WC_Order $order): array
    {
        $refunded = OrderMeta::get($order, 'buckaroo_captures_refunded');
        $decoded = is_string($refunded) ? json_decode($refunded, true) : null;

        return is_array($decoded) ? array_map('strval', $decoded) : [];
    }

    private static function save(WC_Order $order, array $entries): void
    {
        OrderMeta::update($order, self::META_KEY, $entries);
    }
}
