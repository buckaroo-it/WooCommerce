<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Services\Logger;

/**
 * Hands out the Hosted Fields access token to the checkout.
 *
 * A token is only issued for a customer session with a non-empty cart, on a
 * checkout where the credit card gateway is available with Hosted Fields.
 * Tokens are kept in the customer session until shortly before they expire,
 * and the number of new tokens fetched from Buckaroo is limited per session
 * and per client address.
 */
class HostedFieldsController
{
    /** The wc-api request handled by getToken(). */
    public const API_REQUEST = 'WC_Gateway_Buckaroo_creditcard-hosted-fields-token';

    /**
     * Nonce action. The "woocommerce" prefix makes WooCommerce tie the nonce to
     * the customer session for guests.
     */
    public const NONCE_ACTION = 'woocommerce-buckaroo-hosted-fields-token';

    public const AUTH_URL = 'https://auth.buckaroo.io/oauth/token';

    public const SESSION_TOKEN_KEY = 'buckaroo_hosted_fields_token';

    public const SESSION_RATE_KEY = 'buckaroo_hosted_fields_token_requests';

    /** Seconds before the real expiry at which the checkout is told to refresh. */
    public const EXPIRY_MARGIN = 30;

    /** A cached token is only handed out again with at least this many seconds left. */
    public const MIN_REMAINING_LIFETIME = 60;

    /** Window (seconds) in which new tokens are counted. */
    public const RATE_WINDOW = 600;

    /**
     * New tokens per customer session per window. A checkout needs one per
     * token lifetime, plus the odd parallel request or page reload.
     */
    public const SESSION_LIMIT = 10;

    /** New tokens per client address per window, covering several sessions. */
    public const CLIENT_LIMIT = 60;

    /**
     * wc-api callback: send the token response as JSON.
     *
     * @return void
     */
    public static function getToken()
    {
        $result = self::handleRequest();

        wp_send_json($result['body'], $result['status']);
    }

    /**
     * URL the checkout fetches the token from, honouring the site URL and
     * permalink settings.
     */
    public static function tokenUrl(): string
    {
        return add_query_arg(
            '_wpnonce',
            wp_create_nonce(self::NONCE_ACTION),
            WC()->api_request_url(self::API_REQUEST)
        );
    }

    /**
     * Resolve the response for the current request.
     *
     * @return array{status: int, body: array}
     */
    public static function handleRequest(): array
    {
        $gateway = self::gateway();

        if ($gateway === null || ! $gateway->usesHostedFields()) {
            return self::reply(['error' => 'uses_redirect']);
        }

        $session = WC()->session;
        if (! $session || ! $session->has_session()) {
            return self::notAvailable();
        }

        $nonce = isset($_GET['_wpnonce']) && is_string($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash($_GET['_wpnonce']))
            : '';
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return self::notAvailable();
        }

        if (! WC()->cart || WC()->cart->is_empty()) {
            return self::notAvailable();
        }

        $available = WC()->payment_gateways()->get_available_payment_gateways();
        if (! isset($available[CreditCardGateway::GATEWAY_ID])) {
            return self::notAvailable();
        }

        $clientId = (string) $gateway->get_option('hosted_fields_client_id', '');
        $clientSecret = (string) $gateway->get_option('hosted_fields_client_secret', '');
        if ($clientId === '' || $clientSecret === '') {
            Logger::log(__METHOD__, 'Hosted Fields client credentials are not configured.');

            return self::failed(503);
        }

        $client = wp_hash($clientId);
        $cached = $session->get(self::SESSION_TOKEN_KEY);
        if (
            is_array($cached)
            && ($cached['client'] ?? null) === $client
            && is_string($cached['access_token'] ?? null)
            && is_int($cached['expires_at'] ?? null)
            && $cached['expires_at'] - time() >= self::MIN_REMAINING_LIFETIME
        ) {
            return self::tokenReply($cached['access_token'], $cached['expires_at']);
        }

        if (! self::withinRateLimit($session)) {
            return self::reply(['error' => self::genericMessage()], 429);
        }

        $token = self::fetchToken($clientId, $clientSecret);
        if ($token === null) {
            return self::failed(502);
        }

        $session->set(self::SESSION_TOKEN_KEY, [
            'client' => $client,
            'access_token' => $token['access_token'],
            'expires_at' => $token['expires_at'],
        ]);

        return self::tokenReply($token['access_token'], $token['expires_at']);
    }

    private static function gateway(): ?CreditCardGateway
    {
        $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        $gateway = $gateways[CreditCardGateway::GATEWAY_ID] ?? null;

        return $gateway instanceof CreditCardGateway ? $gateway : null;
    }

    /**
     * Count a new token for the session and the client address; false once
     * either is over its limit.
     *
     * @param  \WC_Session  $session
     */
    private static function withinRateLimit($session): bool
    {
        $now = time();

        $sessionCount = self::countRequest($session->get(self::SESSION_RATE_KEY), $now);
        $session->set(self::SESSION_RATE_KEY, $sessionCount);

        $clientKey = 'bk_hf_token_' . md5(self::clientAddress());
        $clientCount = self::countRequest(get_transient($clientKey), $now);
        set_transient($clientKey, $clientCount, max(1, $clientCount['start'] + self::RATE_WINDOW - $now));

        return $sessionCount['count'] <= self::SESSION_LIMIT && $clientCount['count'] <= self::CLIENT_LIMIT;
    }

    /**
     * @param  mixed  $entry
     * @return array{start: int, count: int}
     */
    private static function countRequest($entry, int $now): array
    {
        if (
            ! is_array($entry) || ! is_int($entry['start'] ?? null) || ! is_int($entry['count'] ?? null)
            || $entry['start'] + self::RATE_WINDOW <= $now
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

    /**
     * Request a new token from Buckaroo.
     *
     * @return array{access_token: string, expires_at: int}|null
     */
    private static function fetchToken(string $clientId, string $clientSecret): ?array
    {
        $response = wp_remote_post(self::AUTH_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'scope' => 'hostedfields:save',
                'grant_type' => 'client_credentials',
            ]),
        ]);

        if (is_wp_error($response)) {
            Logger::log(__METHOD__, 'Hosted Fields token request could not be sent (' . $response->get_error_code() . ').');

            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            Logger::log(__METHOD__, 'Hosted Fields token request returned HTTP ' . $status . '.');

            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        $expiresIn = is_array($data) && is_numeric($data['expires_in'] ?? null) ? (int) $data['expires_in'] : 0;

        if (
            ! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === ''
            || $expiresIn <= self::EXPIRY_MARGIN
        ) {
            Logger::log(__METHOD__, 'Hosted Fields token response could not be used.');

            return null;
        }

        return [
            'access_token' => $data['access_token'],
            'expires_at' => time() + $expiresIn,
        ];
    }

    private static function tokenReply(string $token, int $expiresAt): array
    {
        return self::reply([
            'access_token' => $token,
            'expires_in' => max(1, $expiresAt - time() - self::EXPIRY_MARGIN),
        ]);
    }

    private static function notAvailable(): array
    {
        return self::reply(['error' => self::genericMessage()], 403);
    }

    private static function failed(int $status): array
    {
        return self::reply(['error' => self::genericMessage()], $status);
    }

    private static function genericMessage(): string
    {
        return __('The card form could not be loaded. Please try again or choose another payment method.', 'wc-buckaroo-bpe-gateway');
    }

    /**
     * @return array{status: int, body: array}
     */
    private static function reply(array $body, int $status = 200): array
    {
        return ['status' => $status, 'body' => $body];
    }
}
