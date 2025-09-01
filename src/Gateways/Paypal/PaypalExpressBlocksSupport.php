<?php

namespace Buckaroo\Woocommerce\Gateways\Paypal;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;

/**
 * PayPal Express payment method integration for WooCommerce Blocks
 */
class PaypalExpressBlocksSupport extends AbstractPaymentMethodType
{
    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
//        $this->settings = get_option('woocommerce_buckaroo_paypal_settings', []);
//        $gateways = WC()->payment_gateways()->payment_gateways();
//        $this->gateway = isset($gateways['buckaroo_paypal']) ? $gateways['buckaroo_paypal'] : null;
    }
    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'buckaroo-blocks',
            plugins_url('/assets/js/dist/blocks.js', BK_PLUGIN_FILE),
            ['wc-blocks-registry', 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-data'],
            Plugin::VERSION,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('buckaroo-blocks', 'wc-buckaroo-bpe-gateway');
        }

        return ['buckaroo-blocks'];
    }
}
