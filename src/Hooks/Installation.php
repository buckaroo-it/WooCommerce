<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Install\Install;

class Installation {

	public function __construct() {
		Install::installUntrackedInstalation();

		register_activation_hook( BK_PLUGIN_FILE, array( Install::class, 'install' ) );
		register_deactivation_hook( BK_PLUGIN_FILE, array( $this, 'deactivation' ) );
	}

	public function deactivation() {
		CronEvents::unschedule();
	}
}
