<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

class HostedFieldsController
{
    /**
     * Retrieves a token from Buckaroo for Hosted Fields.
     *
     * @return array|bool
     */
    public static function getToken()
    {
        // Replace with your actual Buckaroo API endpoint and credentials.
        $settings = get_option('woocommerce_buckaroo_creditcard_settings');
        if (($settings['creditcardmethod'] ?? 'redirect') == 'redirect') {
            wp_send_json(['error' => 'uses_redirect']);

            return false;
        }

        if (! ($settings['hosted_fields_client_id'] ?? null) || ! ($settings['hosted_fields_client_secret'] ?? null)) {
            wp_send_json(['error' => __('Hosted fields client keys are not provided.', 'wc-buckaroo-bpe-gateway')], 500);
        }

        $tokenUrl = 'https://auth.buckaroo.io/oauth/token';
        $clientId = $settings['hosted_fields_client_id'];
        $clientSecret = $settings['hosted_fields_client_secret'];

        // Prepare the form data
        $formData = [
            'scope' => 'hostedfields:save',
            'grant_type' => 'client_credentials',
        ];
        $authHeader = base64_encode("$clientId:$clientSecret");

        $args = [
            'headers' => [
                'Authorization' => "Basic $authHeader",
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($formData),
        ];

        $response = wp_remote_post($tokenUrl, $args);

        if (is_wp_error($response)) {
            wp_send_json(['error' => $response->get_error_message()], 500);

            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json(['error' => __('Error: Invalid response from server.', 'wc-buckaroo-bpe-gateway')], 500);

            return false;
        }

        if (isset($data['access_token'])) {
            wp_send_json(
                [
                    'access_token' => $data['access_token'],
                    'expires_in' => $data['expires_in'],
                ]
            );

            return true;
        } else {
            $error_message = isset($data['error_message']) ? $data['error_message'] : __('Unknown error occurred.', 'your-textdomain');
            wp_send_json(['error' => __($error_message, 'wc-buckaroo-bpe-gateway')], 500);

            return false;
        }
    }
}
