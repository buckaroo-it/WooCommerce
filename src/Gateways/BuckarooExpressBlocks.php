<?php

namespace Buckaroo\Woocommerce\Gateways;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Buckaroo\Woocommerce\Core\Plugin;

/**
 * Buckaroo Express payment methods integration for WooCommerce Blocks
 */
class BuckarooExpressBlocks extends AbstractPaymentMethodType
{
    protected $name = 'buckaroo_express_blocks';

    protected array $paymentMethods;

    public function __construct(array $paymentMethods = [])
    {
        $this->paymentMethods  = $paymentMethods;
    }

    public function initialize()
    {
        //
    }

    public function get_payment_method_data()
    {
        return [
            'buckarooGateways' => $this->paymentMethods,
        ];
    }

    /**
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
            wp_set_script_translations('buckaroo-blocks', 'wc-buckaroo-bpe-gateway', plugin_dir_path(BK_PLUGIN_FILE) . 'languages');
        }

        return ['buckaroo-blocks'];
    }
}
