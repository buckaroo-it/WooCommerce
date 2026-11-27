<?php

/**
 * Removes the plugin's settings and data when the plugin is deleted.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/vendor/autoload.php';

Buckaroo\Woocommerce\Install\Uninstall::run();
