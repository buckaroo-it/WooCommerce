<?php

/*
Plugin Name: WC Buckaroo BPE Gateway
Plugin URI: http://www.buckaroo.nl
Author: Buckaroo
Author URI: http://www.buckaroo.nl
Description: Buckaroo payment system plugin for WooCommerce.
Version: 4.8.2
Text Domain: wc-buckaroo-bpe-gateway
Domain Path: /languages
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (! defined('BK_PLUGIN_FILE')) {
    define('BK_PLUGIN_FILE', __FILE__);
}

add_action(
    'plugins_loaded',
    function () {
        require_once __DIR__ . '/vendor/autoload.php';

        (new Buckaroo\Woocommerce\Core\Plugin())->init();
    },
    -1
);

/*
 * Declare compatibility with the WooCommerce Cart & Checkout Blocks feature.
 *
 * Without this declaration WooCommerce lists the plugin under the "incompatible"
 * extensions for the `cart_checkout_blocks` feature (see the WooCommerce Checkout
 * block "incompatibleExtensions" data), which is what triggers the Site Editor
 * notice suggesting merchants switch back to the Classic Checkout. The Buckaroo
 * gateways fully support the block checkout, so this declaration is correct.
 */
add_action(
    'before_woocommerce_init',
    function () {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                BK_PLUGIN_FILE,
                true
            );
        }
    }
);
