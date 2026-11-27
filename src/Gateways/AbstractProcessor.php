<?php

namespace Buckaroo\Woocommerce\Gateways;

use WC_Payment_Gateway;

abstract class AbstractProcessor extends WC_Payment_Gateway
{
    public AbstractPaymentGateway $gateway;

    abstract public function getAction(): string;

    /**
     * Get ip
     */
    protected function getIp(): string
    {
        $ipaddress = 'UNKNOWN';
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];
        foreach ($headers as $header) {
            if (! empty($_SERVER[$header]) && is_string($_SERVER[$header])) {
                $ipaddress = sanitize_text_field(wp_unslash($_SERVER[$header]));
                break;
            }
        }
        $ex = explode(',', $ipaddress);
        if (filter_var($ex[0], FILTER_VALIDATE_IP)) {
            return trim($ex[0]);
        }

        return '';
    }

    /**
     * Determine the culture for the transaction based on the browser language.
     *
     * @return string The culture code to be used for the transaction.
     */
    public function determineCulture(): string
    {
        $config = get_option('woocommerce_buckaroo_mastersettings_settings', []);

        // Without a stored culture, use the setting's default (BTI-1880).
        $culture = is_array($config) && is_string($config['culture'] ?? null) && $config['culture'] !== ''
            ? $config['culture']
            : 'dynamic';

        // Check if the dynamic language option is selected.
        if ($culture === 'dynamic') {
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

        return $culture;
    }
}
