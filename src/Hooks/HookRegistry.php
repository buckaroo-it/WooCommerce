<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Install\Migration\MigrationHandler;

class HookRegistry
{
    public array $hooks = [
        Installation::class,
        ReportDownload::class,
        TestCredentials::class,
        AdminHooks::class,
        PaymentSetupScripts::class,
        InitGateways::class,
        DisableGateways::class,
        OrderActions::class,
        CronEvents::class,
        MigrationHandler::class,
    ];

    public function __construct()
    {
        $this->loadHooks();
    }

    public function loadHooks(): void
    {
        foreach ($this->hooks as $hook) {
            new $hook();
        }
    }
}
