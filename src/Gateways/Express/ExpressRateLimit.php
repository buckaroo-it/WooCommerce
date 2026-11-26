<?php

namespace Buckaroo\Woocommerce\Gateways\Express;

/**
 * Limits how often a visitor can start a wallet express transaction, so
 * anonymous callers cannot create orders and Buckaroo calls in bulk
 * (BTI-1874). Counted per WooCommerce session and per client address. The
 * address limit is generous because a shop behind a proxy or CDN sees one
 * address for many shoppers.
 */
final class ExpressRateLimit
{
    public const WINDOW = 600;

    public const SESSION_LIMIT = 10;

    public const CLIENT_LIMIT = 60;

    public const SESSION_KEY = 'buckaroo_express_transactions';

    /**
     * Count one transaction for the session and the client address; false
     * once either is over its limit.
     */
    public static function allow(): bool
    {
        $now = time();
        $withinSessionLimit = true;

        $session = WC()->session;
        if ($session) {
            $sessionCount = self::count($session->get(self::SESSION_KEY), $now);
            $session->set(self::SESSION_KEY, $sessionCount);
            $withinSessionLimit = $sessionCount['count'] <= self::SESSION_LIMIT;
        }

        $clientKey = 'bk_express_tx_' . md5(self::clientAddress());
        $clientCount = self::count(get_transient($clientKey), $now);
        set_transient($clientKey, $clientCount, max(1, $clientCount['start'] + self::WINDOW - $now));

        return $withinSessionLimit && $clientCount['count'] <= self::CLIENT_LIMIT;
    }

    /**
     * @param  mixed  $entry
     * @return array{start: int, count: int}
     */
    private static function count($entry, int $now): array
    {
        if (
            ! is_array($entry) || ! is_int($entry['start'] ?? null) || ! is_int($entry['count'] ?? null)
            || $entry['start'] + self::WINDOW <= $now
        ) {
            return ['start' => $now, 'count' => 1];
        }

        return ['start' => $entry['start'], 'count' => $entry['count'] + 1];
    }

    /** Address of the connecting client as seen by the web server. */
    private static function clientAddress(): string
    {
        $address = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        return $address !== '' ? $address : 'unknown';
    }
}
