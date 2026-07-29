<?php

/*
Plugin Name: Buckaroo Payments for WooCommerce
Plugin URI: http://www.buckaroo.nl
Author: Buckaroo
Author URI: http://www.buckaroo.nl
Description: Accept 40+ payment methods in WooCommerce, including Wero, Klarna, credit cards, Apple Pay and PayPal. Quick to install and easy to use.
Version: 4.7.3
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
