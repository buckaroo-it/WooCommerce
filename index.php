<?php
/*
Plugin Name: WC Buckaroo BPE Gateway
Plugin URI: http://www.buckaroo.nl
Author: Buckaroo
Author URI: http://www.buckaroo.nl
Description: Buckaroo payment system plugin for WooCommerce.
Version: 3.13.2
Text Domain: wc-buckaroo-bpe-gateway
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

use Buckaroo\Woocommerce\Hooks\HookRegistry;

if (!defined('BK_PLUGIN_FILE')) {
    define('BK_PLUGIN_FILE', __FILE__);
}

require_once __DIR__ . "/vendor/autoload.php";


new HookRegistry();
