<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Gateways\In3\In3Gateway;
use Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankProcessor;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;

class PaymentSetupScripts
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'handlePluginsLoaded'], 0);
        add_action('admin_enqueue_scripts', [$this, 'handleAdminAssets']);
        add_action('wp_enqueue_scripts', [$this, 'initFrontendScripts']);
    }

    public function handlePluginsLoaded()
    {
        load_plugin_textdomain(
            'wc-buckaroo-bpe-gateway',
            false,
            dirname(plugin_basename(BK_PLUGIN_FILE)) . '/languages/'
        );

        $transientKey = get_current_user_id() . '_buckaroo_require_woocommerce';
        if (! class_exists('WC_Order')) {
            set_transient($transientKey, true, HOUR_IN_SECONDS);

            return;
        }

        delete_transient($transientKey);
    }

    public function handleAdminAssets(): void
    {
        $pluginDir = plugin_dir_url(BK_PLUGIN_FILE);

        wp_enqueue_style(
            'buckaroo-custom-styles',
            $pluginDir . 'library/css/buckaroo-custom.css',
            [],
            Plugin::VERSION
        );
        wp_enqueue_script(
            'creditcard_capture',
            $pluginDir . 'library/js/creditcard-capture-form.js',
            ['jquery'],
            Plugin::VERSION,
            true
        );
        wp_enqueue_script(
            'buckaroo_admin_utils_js',
            $pluginDir . 'library/js/util.js',
            ['jquery'],
            Plugin::VERSION,
            true
        );
        if (class_exists('WooCommerce')) {
            wp_localize_script(
                'buckaroo_admin_utils_js',
                'buckaroo_php_vars',
                [
                    'version2' => In3Gateway::VERSION2,
                    'in3_v2' => In3Gateway::IN3_V2_TITLE,
                    'in3_v3' => In3Gateway::IN3_V3_TITLE,
                ]
            );
        }
    }

    public function initFrontendScripts()
    {
        if (! class_exists('WC_Order')) {
            return;
        }

        $isCheckout = is_checkout();
        $isCart = is_cart();
        $isProduct = is_product();

        if (! $isCheckout && ! $isCart && ! $isProduct) {
            return;
        }

        $applePayEnabled = $this->isApplePayEnabledForPage($isProduct, $isCart, $isCheckout);

        if (! $isCheckout && ! $applePayEnabled && ! $this->isPaypalExpressEnabledForPage($isProduct, $isCart)) {
            return;
        }

        $pluginDir = plugin_dir_url(BK_PLUGIN_FILE);

        wp_enqueue_style(
            'buckaroo-custom-styles',
            $pluginDir . 'library/css/buckaroo-custom.css',
            [],
            Plugin::VERSION
        );

        wp_enqueue_script(
            'buckaroo_sdk',
            'https://checkout.buckaroo.nl/api/buckaroosdk/script',
            ['jquery'],
            Plugin::VERSION
        );

        if ($applePayEnabled) {
            wp_enqueue_script(
                'buckaroo_apple_pay',
                $pluginDir . 'assets/js/dist/applepay.js',
                ['jquery', 'buckaroo_sdk'],
                Plugin::VERSION,
                true
            );
        }

        wp_enqueue_script(
            'buckaroo_google_pay',
            $pluginDir . 'assets/js/dist/googlepay.js',
            ['jquery', 'buckaroo_sdk'],
            Plugin::VERSION,
            true
        );

        wp_localize_script(
            'buckaroo_sdk',
            'buckaroo_global',
            [
                'ajax_url' => home_url('/'),
                'idin_i18n' => [
                    'general_error' => esc_html__('Something went wrong while processing your identification.'),
                    'bank_required' => esc_html__('You need to select your bank!'),
                ],
                'payByBankLogos' => PayByBankProcessor::getIssuerLogoUrls(),
                'creditCardIssuers' => (new CreditCardGateway())->getCardsList(),
                'locale' => get_locale(),
            ]
        );

        if ($isCheckout) {
            wp_enqueue_script(
                'wc-pf-checkout',
                $pluginDir . 'assets/js/dist/checkout.js',
                ['jquery'],
                Plugin::VERSION,
                true
            );
        }
    }

    private function isApplePayEnabledForPage(bool $isProduct, bool $isCart, bool $isCheckout): bool
    {
        $settings = get_option('woocommerce_buckaroo_applepay_settings');

        if (! is_array($settings) || ($settings['enabled'] ?? '') !== 'yes') {
            return false;
        }

        if ($isCheckout) {
            return true;
        }

        if ($isProduct) {
            return ($settings['button_product'] ?? '') === 'TRUE';
        }

        if ($isCart) {
            return ($settings['button_cart'] ?? '') === 'TRUE';
        }

        return false;
    }

    private function isPaypalExpressEnabledForPage(bool $isProduct, bool $isCart): bool
    {
        $settings = get_option('woocommerce_buckaroo_paypal_settings');

        if (! is_array($settings) || ($settings['enabled'] ?? '') !== 'yes') {
            return false;
        }

        $express = $settings['express'] ?? [];

        if (! is_array($express)) {
            return false;
        }

        if ($isProduct) {
            return in_array(PaypalExpressController::LOCATION_PRODUCT, $express, true);
        }

        if ($isCart) {
            return in_array(PaypalExpressController::LOCATION_CART, $express, true);
        }

        return false;
    }
}
