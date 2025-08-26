<?php

namespace Buckaroo\Woocommerce\Services;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Logger;
use Exception;
use WC;

/**
 * Class AutoConfiguration
 *
 * Handles automatic configuration of payment methods based on active subscriptions
 */
class AutoConfiguration
{
    public function __construct()
    {
        add_action('wp_ajax_buckaroo_auto_configure', array($this, 'handleAjaxRequest'));
    }

    /**
     * Handle AJAX request for auto configuration
     */
    public function handleAjaxRequest()
    {
        try {
            $activeServices = $this->getActiveSubscriptions();

            if ($activeServices === false) {
                wp_die(esc_html__('Failed to retrieve active subscriptions. Please check your credentials.', 'wc-buckaroo-bpe-gateway'));
            }

            if (empty($activeServices)) {
                wp_die(esc_html__('No active subscriptions found. Please check your Buckaroo account.', 'wc-buckaroo-bpe-gateway'));
            }

            $result = $this->configurePaymentMethods($activeServices);
            wp_die(esc_html($result['message']));
        } catch (Exception $e) {
            wp_die(esc_html__('Configuration failed: ', 'wc-buckaroo-bpe-gateway') . esc_html($e->getMessage()));
        }
    }

    /**
     * Get active subscriptions from Buckaroo API
     *
     * @return array|false Array of active services or false on failure
     */
    public function getActiveSubscriptions()
    {
        try {
            $config = get_option('woocommerce_buckaroo_mastersettings_settings', array());

            if (empty($config['merchantkey']) || empty($config['secretkey'])) {
                throw new Exception('Missing merchant credentials');
            }

            $client = new BuckarooClient($config['mode'] ?? 'test');

            return $this->parseActiveSubscriptionsResponse(
                $client->getActiveSubscriptions()
            );
        } catch (Exception $e) {
            Logger::log(__METHOD__ . '|Error|', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Parse the GetActiveSubscriptions response (array format)
     *
     * @param array $response API response
     * @return array Array of active services
     */
    private function parseActiveSubscriptionsResponse($response)
    {
        $activeServices = array();

        if (!is_array($response)) {
            return $activeServices;
        }

        foreach ($response as $service) {
            if (isset($service['serviceCode'], $service['currencies'])) {
                $activeServices[] = array(
                    'service_code' => $service['serviceCode'],
                    'currencies' => $service['currencies']
                );
            }
        }

        return $activeServices;
    }

    /**
     * Configure payment methods automatically based on active subscriptions
     *
     * @param array $activeServices Array of active services from API
     * @return array Result of the configuration process
     */
    public function configurePaymentMethods($activeServices)
    {
        $result = array(
            'success' => true,
            'message' => '',
            'configured_methods' => array(),
            'errors' => array()
        );

        try {
            $buckarooGateways = $this->getBuckarooGateways();
            $gatewaysToEnable = array();

            foreach ($activeServices as $service) {
                $serviceCode = $service['service_code'];

                foreach ($buckarooGateways as $gateway) {
                    if (method_exists($gateway, 'getServiceCode') && $gateway->getServiceCode() === $serviceCode) {
                        if (!in_array($gateway, $gatewaysToEnable, true)) {
                            $gatewaysToEnable[] = $gateway;
                        }
                        break;
                    }
                }
            }

            foreach ($gatewaysToEnable as $gateway) {
                $this->enablePaymentMethod($gateway, $result);
            }

            if (empty($result['configured_methods'])) {
                $result['success'] = false;
                $result['message'] = __('No payment methods were configured. Please check your active subscriptions.', 'wc-buckaroo-bpe-gateway');
            } else {
                $result['message'] = sprintf(
                    __('Successfully configured %d payment method(s): %s', 'wc-buckaroo-bpe-gateway'),
                    count($result['configured_methods']),
                    implode(', ', $result['configured_methods'])
                );
            }
        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = __('Configuration failed: ', 'wc-buckaroo-bpe-gateway') . $e->getMessage();
            Logger::log(__METHOD__ . '|ConfigurationError|', array('error' => $e->getMessage()));
        }

        return $result;
    }

    /**
     * Get all Buckaroo payment gateways
     *
     * @return array Array of Buckaroo gateways
     */
    private function getBuckarooGateways()
    {
        $allGateways = WC()->payment_gateways->payment_gateways();
        $buckarooGateways = array();

        foreach ($allGateways as $gateway) {
            if (str_starts_with($gateway->id, 'buckaroo_')) {
                $buckarooGateways[] = $gateway;
            }
        }

        return $buckarooGateways;
    }

    /**
     * Enable a specific payment method and set it to live mode
     *
     * @param AbstractPaymentGateway $gateway Gateway object to enable
     * @param array $result Reference to result array
     */
    private function enablePaymentMethod($gateway, &$result)
    {
        try {
            $gatewayId = $gateway->id;
            $optionName = 'woocommerce_' . $gatewayId . '_settings';
            $settings = get_option($optionName, array());

            $settings['enabled'] = 'yes';
            $settings['mode'] = 'live';

            update_option($optionName, $settings);

            $gatewayTitle = method_exists($gateway, 'get_method_title')
                ? $gateway->get_method_title()
                : $gateway->get_title();
            $gatewayTitle = str_replace('Buckaroo ', '', $gatewayTitle);

            $result['configured_methods'][] = $gatewayTitle;

            Logger::log(__METHOD__ . '|Enabled|', array(
                'gateway_id' => $gatewayId,
                'gateway_title' => $gatewayTitle
            ));
        } catch (Exception $e) {
            $result['errors'][] = sprintf(
                __('Failed to configure %s: %s', 'wc-buckaroo-bpe-gateway'),
                $gateway->id,
                $e->getMessage()
            );
            Logger::log(__METHOD__ . '|EnableError|', array(
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage()
            ));
        }
    }
}
