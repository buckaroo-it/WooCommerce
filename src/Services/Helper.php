<?php

namespace Buckaroo\Woocommerce\Services;

use Automattic\WooCommerce\Admin\Overrides\Order;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;
use WC_Order;
use WC_Payment_Gateways;
use WP_Post;

class Helper
{
    public static function handleUnsuccessfulPayment($status_code): bool
    {
        return in_array($status_code, [ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_USER, ResponseStatus::BUCKAROO_STATUSCODE_REJECTED]);
    }

    public static function isOrderInstance($instance): bool
    {
        return $instance instanceof Order || $instance instanceof WC_Order;
    }

    public static function findOrder($order_id)
    {
        return self::isWooCommerceVersion3OrGreater() ?
            wc_get_order($order_id) : new WC_Order($order_id);
    }

    public static function resolveOrder($input)
    {
        if (static::isOrderInstance($input)) {
            return $input;
        }

        if ($input instanceof WP_Post || is_scalar($input)) {
            return self::findOrder($input);
        }

        return null;
    }

    /**
     * Checks if WooCommerce Version 3 or greater is installed
     */
    public static function isWooCommerceVersion3OrGreater(): bool
    {
        return substr(WC()->version, 0, 1) >= 3;
    }

    public static function roundAmount($amount)
    {
        if (is_scalar($amount) && is_numeric($amount)) {
            return (float) number_format($amount, 2, '.', '');
        }

        return 0;
    }

    public static function checkCreditCardProvider($creditCardProvider)
    {
        $creditCardsProvidersList = static::getCreditcardsProviders();
        foreach ($creditCardsProvidersList as $provider) {
            if ($provider['servicename'] === $creditCardProvider) {
                return $provider;
            }
        }

        return false;
    }

    public static function getCreditcardsProviders()
    {
        $paymentgateways = WC_Payment_Gateways::instance();
        $creditcard = $paymentgateways->payment_gateways()['buckaroo_creditcard'];

        return $creditcard->getCardsList();
    }

    /**
     * Cancel order and create new if order_awaiting_payment exists
     */
    public static function resetOrder(): void
    {
        $order_id = WC()->session->order_awaiting_payment;
        if ($order_id) {
            $order = wc_get_order($order_id);

            $status = get_post_status($order_id);

            if (($status == 'wc-failed' || $status == 'wc-cancelled') && wc_notice_count('error') == 0) {
                // Add generated hash to order for WooCommerce versions later than 2.5
                if (version_compare(WC()->version, '2.5', '>')) {
                    $order->cart_hash = md5(json_encode(wc_clean(WC()->cart->get_cart_for_session())) . WC()->cart->total);
                }

                if (version_compare(WC()->version, '3.6', '>=')) {
                    Logger::log('Update status 7. Order status: cancelled');
                    $order->update_status('cancelled');
                } else {
                    $newOrder = wc_create_order($order);
                    WC()->session->order_awaiting_payment = $newOrder->get_id();
                }
            }
        }
    }

    public static function processCheckRedirectRequired(ResponseParser $responseParser)
    {
        if ($responseParser->hasRedirect()) {
            return [
                'result' => 'success',
                'redirect' => $responseParser->getRedirectUrl(),
            ];
        }

        return false;
    }

    public static function getAllGendersForPaymentMethods(): array
    {
        $defaultGenders = [
            'male' => 1,
            'female' => 2,
            'they' => 0,
            'unknown' => 9,
        ];

        $billinkGenders = [
            'male' => 'Male',
            'female' => 'Female',
            'they' => 'Unknown',
            'unknown' => 'Unknown',
        ];

        $klarnaGenders = [
            'male' => 'male',
            'female' => 'female',
        ];

        return [
            'buckaroo-payperemail' => $defaultGenders,
            'buckaroo-billink' => $billinkGenders,
            'buckaroo-klarnakp' => $klarnaGenders,
            'buckaroo-klarnapay' => $klarnaGenders,
            'buckaroo-klarnapii' => $klarnaGenders,
        ];
    }

    public static function translateGender($genderKey)
    {
        switch ($genderKey) {
            case 'male':
                return __('He/him', 'wc-buckaroo-bpe-gateway');
            case 'female':
                return __('She/her', 'wc-buckaroo-bpe-gateway');
            case 'they':
                return __('They/them', 'wc-buckaroo-bpe-gateway');
            case 'unknown':
                return __('I prefer not to say', 'wc-buckaroo-bpe-gateway');
            default:
                return $genderKey;
        }
    }

    /**
     * @param  string  $key
     * @return string $val
     */
    public static function get($key, $paymentId = null)
    {
        $paymentId = $paymentId ? 'woocommerce_buckaroo_' . $paymentId . '_settings' : ($GLOBALS['plugin_id'] ?? '');
        $options = $paymentId ? get_option($paymentId, []) : [];

        $options['enabled'] = $options['enabled'] ?? false;
        $masterOptions = get_option('woocommerce_buckaroo_mastersettings_settings', []);

        if (is_array($masterOptions)) {
            unset($masterOptions['enabled']);
            $options = array_replace($options, $masterOptions);
        }

        return $options[$key] ?? null;
    }
}
