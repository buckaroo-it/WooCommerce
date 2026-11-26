<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Install\Install;
use Buckaroo\Woocommerce\Install\Uninstall;
use Buckaroo\Woocommerce\Services\LoggerStorage;

class Test_Uninstall extends WP_UnitTestCase
{
    /** @var array<string, mixed> */
    private $options = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->pluginOptions() as $name) {
            $this->options[$name] = get_option($name, null);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->options as $name => $value) {
            if ($value === null) {
                delete_option($name);
            } else {
                update_option($name, $value);
            }
        }

        parent::tearDown();
    }

    public function test_plugin_options_and_transients_are_removed(): void
    {
        update_option('woocommerce_buckaroo_mastersettings_settings', ['secretkey' => 'secret']);
        update_option('woocommerce_buckaroo_ideal_settings', ['enabled' => 'yes']);
        update_option(Install::DATABASE_VERSION_KEY, '4.11.0');
        update_option('buckaroo_order_meta_hpos_complete', 'yes');
        set_transient('buckaroo_plugin_updated', true);
        set_transient('1buckarooAdminNotice', ['type' => 'error']);
        add_option('_buckaroo_klarna_capture_1_abc', 'claimed', '', 'no');
        update_option('woocommerce_bacs_settings', ['enabled' => 'yes']);

        Uninstall::cleanSite();

        $this->assertFalse(get_option('_buckaroo_klarna_capture_1_abc'));

        foreach ($this->pluginOptions() as $name) {
            $this->assertFalse(get_option($name), $name);
        }
        $this->assertFalse(get_transient('buckaroo_plugin_updated'));
        $this->assertFalse(get_transient('1buckarooAdminNotice'));
        $this->assertSame(['enabled' => 'yes'], get_option('woocommerce_bacs_settings'));

        delete_option('woocommerce_bacs_settings');
    }

    public function test_plugin_tables_are_dropped(): void
    {
        global $wpdb;

        $queries = [];
        $record = static function ($query) use (&$queries) {
            if (stripos($query, 'DROP') === 0) {
                $queries[] = $query;
            }

            return $query;
        };
        add_filter('query', $record, 1);

        try {
            Uninstall::cleanSite();
        } finally {
            remove_filter('query', $record, 1);
        }

        $dropped = implode("\n", $queries);
        $this->assertStringContainsString($wpdb->prefix . 'woocommerce_buckaroo_transactions', $dropped);
        $this->assertStringContainsString($wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE, $dropped);
    }

    public function test_scheduled_log_cleanup_is_removed(): void
    {
        wp_schedule_event(time(), 'daily', 'buckaroo_clean_logger_storage');

        Uninstall::cleanSite();

        $this->assertFalse(wp_next_scheduled('buckaroo_clean_logger_storage'));
    }

    public function test_log_directory_is_removed(): void
    {
        $suffix = str_repeat('a', 32);
        update_option(LoggerStorage::STORAGE_FILE_DIRECTORY_OPTION, $suffix);
        $uploads = wp_upload_dir(null, false);
        $directory = trailingslashit($uploads['basedir']) . 'buckaroo-logs-' . $suffix . '/';
        wp_mkdir_p($directory);
        file_put_contents($directory . '01-01-2026.log', 'line');
        file_put_contents($directory . '.htaccess', 'deny from all');

        Uninstall::cleanSite();

        $this->assertDirectoryDoesNotExist($directory);
    }

    /**
     * @return string[]
     */
    private function pluginOptions(): array
    {
        return [
            'woocommerce_buckaroo_mastersettings_settings',
            'woocommerce_buckaroo_ideal_settings',
            Install::DATABASE_VERSION_KEY,
            'buckaroo_order_meta_hpos_complete',
            LoggerStorage::STORAGE_FILE_DIRECTORY_OPTION,
        ];
    }
}
