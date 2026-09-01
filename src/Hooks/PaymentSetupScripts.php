<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankProcessor;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;
use BuckarooDeps\Buckaroo\Resources\Constants\Endpoints;

class PaymentSetupScripts
{
    private const SDK_SCRIPT_PATH = 'api/buckaroosdk/script';

    /**
     * Apple's official Apple Pay JS SDK.
     *
     * Provides the <apple-pay-button> web component, ApplePaySession.applePayCapabilities()
     * and the cross-device (QR-code) handoff so Apple Pay works in every browser
     * (Chrome, Edge, Firefox, Safari). The Buckaroo SDK is still used for the
     * ApplePaySession orchestration and merchant validation.
     */
    private const APPLE_PAY_SDK_URL = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';

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

        wp_enqueue_script(
            'buckaroo_admin_settings',
            $pluginDir . 'library/js/admin-settings.js',
            [],
            Plugin::VERSION,
            true
        );

        wp_localize_script('buckaroo_admin_settings', 'buckarooAdminSettings', ['hostedFields' => $this->hostedFieldsIds()]);

        wp_localize_script(
            'buckaroo_admin_utils_js',
            'buckarooAdminAjax',
            [
                'nonce' => wp_create_nonce('buckaroo_admin_ajax'),
            ]
        );
    }

    /**
     * Field ids the hosted-fields rows are keyed by, mirroring the gateway's own
     * plugin_id . id . '_' . key naming so the enqueue does not depend on
     * WooCommerce having registered the gateway yet.
     */
    private function hostedFieldsIds(): array
    {
        $prefix = 'woocommerce_' . CreditCardGateway::GATEWAY_ID . '_';

        return [
            'select' => $prefix . 'creditcardmethod',
            'clientId' => $prefix . 'hosted_fields_client_id',
            'clientSecret' => $prefix . 'hosted_fields_client_secret',
        ];
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
            $this->getBuckarooSdkUrl(),
            ['jquery'],
            Plugin::VERSION
        );

        if ($applePayEnabled) {
            // Apple's official SDK (web component + applePayCapabilities + QR handoff).
            //
            // IMPORTANT: this is enqueued standalone in the <head> and is NOT added
            // to the WP dependency array of `buckaroo_apple_pay`. The WooCommerce
            // Blocks integration (`buckaroo-blocks`) depends on `buckaroo_apple_pay`
            // and recursively validates its dependency tree; adding this external
            // CDN handle to that tree caused Blocks to deactivate the whole Buckaroo
            // integration ("dependency not registered"). Loading it in the head
            // guarantees the <apple-pay-button> element and applePayCapabilities()
            // are available before the footer bundle runs, without the coupling.
            wp_enqueue_script(
                'apple_pay_sdk',
                self::APPLE_PAY_SDK_URL,
                [],
                null,
                false
            );

            // Loaded in the HEAD (not the footer): the Blocks bundle
            // (`buckaroo-blocks`) deliberately has no script dependency on this
            // handle (see BuckarooBlocksScript), so footer order between the two
            // is not guaranteed. The standard Apple Pay checkout method needs
            // window.BuckarooApplePay before React mounts it. Its dependencies
            // (jquery, buckaroo_sdk) load in the head as well, and the bundle
            // only registers globals at top level (DOM work waits for jQuery
            // ready), so head loading is safe.
            wp_enqueue_script(
                'buckaroo_apple_pay',
                $pluginDir . 'assets/js/dist/applepay.js',
                ['jquery', 'buckaroo_sdk'],
                $this->bundleVersion('applepay'),
                false
            );
        }

        wp_enqueue_script(
            'buckaroo_google_pay',
            $pluginDir . 'assets/js/dist/googlepay.js',
            ['jquery', 'buckaroo_sdk'],
            $this->bundleVersion('googlepay'),
            true
        );

        wp_localize_script(
            'buckaroo_sdk',
            'buckaroo_global',
            [
                'ajax_url' => home_url('/'),
                'admin_ajax_url' => admin_url('admin-ajax.php'),
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
            // The classic-checkout wallet methods rely on window.BuckarooApplePay /
            // window.BuckarooGooglePay exposed by the wallet bundles.
            $checkoutDeps = ['jquery', 'jquery-ui-datepicker', 'buckaroo_google_pay'];
            if ($applePayEnabled) {
                $checkoutDeps[] = 'buckaroo_apple_pay';
            }
            wp_enqueue_script(
                'wc-pf-checkout',
                $pluginDir . 'assets/js/dist/checkout.js',
                $checkoutDeps,
                $this->bundleVersion('checkout'),
                true
            );
        }
    }

    /**
     * Content-derived cache-busting version for a compiled bundle, so a
     * rebuild is never masked by a stale browser cache (the plugin version
     * does not change between builds). Prefers the hash generated by the
     * dependency-extraction plugin, then the file mtime.
     */
    private function bundleVersion(string $name): string
    {
        $base = plugin_dir_path(BK_PLUGIN_FILE) . 'assets/js/dist/' . $name;

        if (is_readable($base . '.asset.php')) {
            $asset = include $base . '.asset.php';
            if (is_array($asset) && ! empty($asset['version'])) {
                return (string) $asset['version'];
            }
        }

        if (is_readable($base . '.js')) {
            return (string) filemtime($base . '.js');
        }

        return Plugin::VERSION;
    }

    /**
     * Resolve the Buckaroo Client SDK url for the current environment.
     *
     * When PayPal is enabled and in test mode the sandbox SDK build is loaded
     * (from the SDK's TEST endpoint), which exposes the PayPal sandbox client
     * ids and Base.setTestMode(). Otherwise the live endpoint is used so live
     * stores are unaffected.
     *
     * @return string
     */
    private function getBuckarooSdkUrl(): string
    {
        $base = $this->isPaypalTestMode() ? Endpoints::TEST : Endpoints::LIVE;

        return rtrim($base, '/') . '/' . self::SDK_SCRIPT_PATH;
    }

    /**
     * Whether the PayPal gateway is enabled and running in test mode.
     *
     * @return bool
     */
    private function isPaypalTestMode(): bool
    {
        $settings = get_option('woocommerce_buckaroo_paypal_settings');

        return is_array($settings)
            && ($settings['enabled'] ?? '') === 'yes'
            && strtolower((string) ($settings['mode'] ?? '')) === 'test';
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
