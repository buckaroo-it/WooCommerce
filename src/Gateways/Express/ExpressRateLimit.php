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

    /** Seconds a request waits for another one to finish counting. */
    private const LOCK_WAIT = 2;

    /**
     * Count one transaction for the session and the client address; false
     * once either is over its limit. The session count only holds a visitor
     * that keeps the session cookie; the address count is the limit a
     * caller cannot shed.
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

        return self::countClient($now) && $withinSessionLimit;
    }

    /**
     * The database lock the address count is updated under, for the current
     * client address and site.
     */
    public static function lockName(): string
    {
        global $wpdb;

        return 'buckaroo_express_rate_' . md5($wpdb->prefix . self::clientAddress());
    }

    /**
     * Count one transaction for the client address; false once it is over its
     * limit. The count is read and written under a lock, so parallel requests
     * cannot overwrite each other's count. A request that cannot take the
     * lock in time is refused.
     */
    private static function countClient(int $now): bool
    {
        global $wpdb;

        $lock = self::lockName();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock has to be taken in the database.
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock, self::LOCK_WAIT)) !== 1) {
            return false;
        }

        try {
            $clientKey = 'bk_express_tx_' . md5(self::clientAddress());
            $clientCount = self::count(get_transient($clientKey), $now);
            set_transient($clientKey, $clientCount, max(1, $clientCount['start'] + self::WINDOW - $now));

            return $clientCount['count'] <= self::CLIENT_LIMIT;
        } finally {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Releases the lock taken above.
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
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
