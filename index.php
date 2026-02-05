<?php

/*
Plugin Name: WC Buckaroo BPE Gateway
Plugin URI: http://www.buckaroo.nl
Author: Buckaroo
Author URI: http://www.buckaroo.nl
Description: Buckaroo payment system plugin for WooCommerce.
Version: 4.7.0
Text Domain: wc-buckaroo-bpe-gateway
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
