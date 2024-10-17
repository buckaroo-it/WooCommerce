<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Core\Plugin;

class PluginsLoaded
{
    public function __construct()
    {
        add_action('plugins_loaded', [new Plugin(), 'register'], 0);
    }
}