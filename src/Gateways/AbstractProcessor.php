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
        if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (! empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (! empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (! empty($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (! empty($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        $ex = explode(',', sanitize_text_field($ipaddress));
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

        // Check if the dynamic language option is selected.
        if ($config['culture'] == 'dynamic') {
            // Get the first two characters of the browser's language setting.
            $browserLanguage = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            // Map of supported browser languages to Buckaroo culture codes.
            $supportedLanguages = [
                'nl' => 'nl-NL',
                'en' => 'en-US',
                'de' => 'de-DE',
                'fr' => 'fr-FR',
            ];

            // Use the browser language if supported, otherwise default to English.
            return $supportedLanguages[$browserLanguage] ?? 'en-US';
        }

        return $config['culture'];
    }
}
