<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Credential settings of Buckaroo gateways in the WooCommerce REST API
 * (wc/v2 and wc/v3 payment_gateways): their values are left out of responses,
 * and a blank value in an update keeps the saved credential, as in the
 * settings form.
 */
class RestGatewaySettings
{
    private const GATEWAY_ROUTE = '#^/wc/v[23]/payment_gateways/(?P<id>[\w-]+)/?$#';

    public function __construct()
    {
        add_filter('woocommerce_rest_prepare_payment_gateway', [$this, 'hideCredentials'], 10, 2);
        add_filter('rest_request_before_callbacks', [$this, 'keepBlankCredentials'], 10, 3);
    }

    /**
     * @param  mixed  $response
     * @param  mixed  $gateway
     * @return mixed
     */
    public function hideCredentials($response, $gateway)
    {
        if (! $response instanceof WP_REST_Response || ! $gateway instanceof AbstractPaymentGateway) {
            return $response;
        }

        $data = $response->get_data();
        if (! is_array($data) || ! is_array($data['settings'] ?? null)) {
            return $response;
        }

        foreach (self::credentialKeys($gateway) as $key) {
            if (is_array($data['settings'][$key] ?? null)) {
                $data['settings'][$key]['value'] = '';
            }
        }

        $response->set_data($data);

        return $response;
    }

    /**
     * @param  mixed  $response
     * @param  mixed  $handler
     * @param  mixed  $request
     * @return mixed
     */
    public function keepBlankCredentials($response, $handler, $request)
    {
        if (
            ! $request instanceof WP_REST_Request
            || ! in_array($request->get_method(), ['POST', 'PUT', 'PATCH'], true)
            || ! preg_match(self::GATEWAY_ROUTE, $request->get_route(), $match)
        ) {
            return $response;
        }

        $settings = $request->get_param('settings');
        if (! is_array($settings) || ! function_exists('WC') || ! WC()->payment_gateways()) {
            return $response;
        }

        $gateway = WC()->payment_gateways()->payment_gateways()[$match['id']] ?? null;
        if (! $gateway instanceof AbstractPaymentGateway) {
            return $response;
        }

        $changed = false;
        foreach (self::credentialKeys($gateway) as $key) {
            if (
                array_key_exists($key, $settings)
                && ($settings[$key] === null || (is_string($settings[$key]) && trim($settings[$key]) === ''))
            ) {
                unset($settings[$key]);
                $changed = true;
            }
        }

        if ($changed) {
            $request->set_param('settings', $settings);
        }

        return $response;
    }

    /**
     * Settings of the gateway that hold credentials: its password fields.
     *
     * @return string[]
     */
    public static function credentialKeys(AbstractPaymentGateway $gateway): array
    {
        if (empty($gateway->form_fields)) {
            $gateway->init_form_fields();
        }

        $keys = [];
        foreach ($gateway->get_form_fields() as $key => $field) {
            if (is_array($field) && ($field['type'] ?? '') === 'password') {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }
}
