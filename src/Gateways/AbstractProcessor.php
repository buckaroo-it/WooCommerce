<?php

namespace Buckaroo\Woocommerce\Gateways;

use WC_Payment_Gateway;

abstract class AbstractProcessor extends WC_Payment_Gateway
{
    public AbstractPaymentGateway $gateway;

    abstract public function getAction(): string;

    /**
     * IP address of the shopper.
     *
     * REMOTE_ADDR is used, unless it belongs to a trusted proxy: then the
     * nearest address in X-Forwarded-For that is not a trusted proxy is used.
     * Private and reserved addresses count as trusted proxies; other proxies
     * can be added with the `buckaroo_trusted_proxies` filter.
     */
    protected function getIp(): string
    {
        $client = $this->getServerIp('REMOTE_ADDR');

        if ($client === '' || ! $this->isTrustedProxy($client)) {
            return $client;
        }

        $forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']))
            : '';

        foreach (array_reverse(explode(',', $forwarded)) as $hop) {
            $hop = $this->normalizeIp(trim($hop));

            if ($hop === '') {
                break;
            }

            $client = $hop;

            if (! $this->isTrustedProxy($hop)) {
                break;
            }
        }

        return $client;
    }

    private function getServerIp(string $key): string
    {
        if (! isset($_SERVER[$key]) || ! is_string($_SERVER[$key])) {
            return '';
        }

        return $this->normalizeIp(trim(sanitize_text_field(wp_unslash($_SERVER[$key]))));
    }

    /**
     * A valid IP address, with an IPv4-mapped IPv6 address (::ffff:a.b.c.d)
     * written as IPv4; '' when it is not an IP address.
     */
    private function normalizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        if (stripos($ip, '::ffff:') === 0 && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return substr($ip, 7);
        }

        return $ip;
    }

    private function isTrustedProxy(string $ip): bool
    {
        /**
         * IP addresses of reverse proxies or load balancers in front of the
         * shop whose X-Forwarded-For header may be used for the shopper's IP.
         *
         * @param  string[]  $proxies
         */
        $proxies = apply_filters('buckaroo_trusted_proxies', []);

        if (is_array($proxies) && in_array($ip, $proxies, true)) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Determine the culture for the transaction based on the browser language.
     *
     * @return string The culture code to be used for the transaction.
     */
    public function determineCulture(): string
    {
        $config = get_option('woocommerce_buckaroo_mastersettings_settings', []);

        // Check if the dynamic language option is selected.
        if ($config['culture'] == 'dynamic') {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $language = substr((string) $locale, 0, 2);

            $supportedLanguages = [
                'nl' => 'nl-NL',
                'en' => 'en-US',
                'de' => 'de-DE',
                'fr' => 'fr-FR',
            ];

            return $supportedLanguages[$language] ?? 'en-US';
        }

        return $config['culture'];
    }
}
