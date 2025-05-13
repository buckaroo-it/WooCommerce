<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Install\Install;

class Installation
{
    public function __construct()
    {
        Install::installUntrackedInstalation();

        register_activation_hook(BK_PLUGIN_FILE, [Install::class, 'install']);
        register_deactivation_hook(BK_PLUGIN_FILE, [$this, 'deactivation']);
    }

    public function deactivation()
    {
        CronEvents::unschedule();
    }
}
