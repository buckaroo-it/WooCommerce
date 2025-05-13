<?php

namespace Buckaroo\Woocommerce\Install;

use Buckaroo\Woocommerce\Install\Migration\MigrationHandler;

/**
 * WC_Install Class
 */
class Install
{
    public const DATABASE_VERSION_KEY = 'BUCKAROO_BPE_VERSION';

    /**
     * Do a install if the plugin was installed prior to 2.24.1
     *
     * @return void
     */
    public static function installUntrackedInstalation()
    {
        if (self::isUntrackedInstall()) {
            self::install();
        }
    }

    public static function isUntrackedInstall()
    {
        return self::get_db_version() === false;
    }

    /**
     * Get database version
     *
     * @return void
     */
    public static function get_db_version()
    {
        return get_option(self::DATABASE_VERSION_KEY);
    }

    /**
     * @return bool (true)
     */
    public static function install()
    {
        if (self::isInstalled()) {
            return;
        }
        // fresh install
        self::set_db_version('0.0.0.0');
        (new MigrationHandler())->handle();

        return true;
    }

    public static function isInstalled()
    {
        return self::get_db_version() !== false;
    }

    /**
     * Set database version
     *
     * @param  string  $version
     * @return void
     */
    public static function set_db_version($version)
    {
        update_option(self::DATABASE_VERSION_KEY, $version);
    }
}
