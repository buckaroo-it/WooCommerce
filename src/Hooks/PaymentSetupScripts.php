<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Gateways\In3\In3Gateway;
use Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankProcessor;
use Buckaroo\Woocommerce\Services\Config;

class PaymentSetupScripts
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'handleAdminAssets']);
        add_action('enqueue_block_assets', [$this, 'handleBlockAssets']);
        add_action('wp_enqueue_scripts', [$this, 'initFrontendScripts']);
    }

    public function handleAdminAssets(): void
    {
        $pluginDir = plugin_dir_url(BK_PLUGIN_FILE);

        wp_enqueue_style(
            'buckaroo-custom-styles',
            $pluginDir . 'library/css/buckaroo-custom.css',
            array(),
            Config::VERSION
        );
        wp_enqueue_script(
            'creditcard_capture',
            $pluginDir . 'library/js/9yards/creditcard-capture-form.js',
            array('jquery'),
            Config::VERSION,
            true
        );
        wp_enqueue_script(
            'buckaroo_certificate_management_js',
            $pluginDir . 'library/js/9yards/upload_certificate.js',
            array('jquery'),
            Config::VERSION,
            true
        );
        if (class_exists('WooCommerce')) {
            wp_localize_script(
                'buckaroo_certificate_management_js',
                'buckaroo_php_vars',
                array(
                    'version2' => In3Gateway::VERSION2,
                    'in3_v2' => In3Gateway::IN3_V2_TITLE,
                    'in3_v3' => In3Gateway::IN3_V3_TITLE,
                )
            );
        }
        wp_enqueue_script(
            'buckaroo-block-script',
            $pluginDir . 'assets/js/dist/blocks.js',
            array('wp-blocks', 'wp-element')
        );
    }

    public function handleBlockAssets()
    {
        wp_enqueue_script(
            'buckaroo-blocks',
            plugins_url('/assets/js/dist/blocks.js', BK_PLUGIN_FILE),
            array('wc-blocks-registry'),
            Config::VERSION,
            true
        );
    }

    public function initFrontendScripts()
    {
        if (class_exists('WC_Order') && (is_product() || is_checkout() || is_cart())) {
            wp_enqueue_style(
                'buckaroo-custom-styles',
                plugin_dir_url(BK_PLUGIN_FILE) . 'library/css/buckaroo-custom.css',
                array(),
                Config::VERSION
            );

            wp_enqueue_script(
                'buckaroo_sdk',
                'https://checkout.buckaroo.nl/api/buckaroosdk/script',
                // 'https://testcheckout.buckaroo.nl/api/buckaroosdk/script',
                array('jquery'),
                Config::VERSION
            );

            wp_enqueue_script(
                'buckaroo_apple_pay',
                plugin_dir_url(BK_PLUGIN_FILE) . 'assets/js/dist/applepay.js',
                array('jquery', 'buckaroo_sdk'),
                Config::VERSION,
                true
            );

            wp_localize_script(
                'buckaroo_sdk',
                'buckaroo_global',
                array(
                    'ajax_url' => home_url('/'),
                    'idin_i18n' => array(
                        'general_error' => esc_html__('Something went wrong while processing your identification.'),
                        'bank_required' => esc_html__('You need to select your bank!'),
                    ),
                    'payByBankLogos' => PayByBankProcessor::getIssuerLogoUrls(),
                )
            );

        }

        if (class_exists('WC_Order') && is_checkout()) {
            wp_enqueue_script(
                'wc-pf-checkout',
                plugin_dir_url(BK_PLUGIN_FILE) . 'assets/js/dist/checkout.js',
                array('jquery'),
                Config::VERSION,
                true
            );
        }
    }
}