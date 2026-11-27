<?php

namespace Buckaroo\Woocommerce\Install;

use Buckaroo\Woocommerce\Services\LoggerStorage;

/**
 * Removes the data the plugin stores when it is deleted: its settings and
 * other options, transients, scheduled events, the log and transaction tables
 * and the log files. Order data is kept.
 */
class Uninstall
{
    /**
     * Option name patterns for the LIKE query, before escaping.
     */
    private const OPTION_PREFIXES = [
        'woocommerce_buckaroo_',
        'buckaroo_',
        '_buckaroo_',
        '_transient_buckaroo_',
        '_transient_timeout_buckaroo_',
    ];

    private const ACTION_GROUPS = ['buckaroo', 'buckaroo-klarna-capture'];

    public static function run(): void
    {
        if (is_multisite() && function_exists('get_sites')) {
            foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
                switch_to_blog((int) $siteId);
                self::cleanSite();
                restore_current_blog();
            }

            return;
        }

        self::cleanSite();
    }

    public static function cleanSite(): void
    {
        self::unscheduleEvents();
        self::deleteLogFiles();
        self::dropTables();
        self::deleteOptions();
    }

    private static function unscheduleEvents(): void
    {
        wp_clear_scheduled_hook('buckaroo_clean_logger_storage');

        if (function_exists('as_unschedule_all_actions')) {
            foreach (self::ACTION_GROUPS as $group) {
                as_unschedule_all_actions('', [], $group);
            }
        }
    }

    /**
     * Log files in the plugin's own directory in uploads. A directory set
     * with the `buckaroo_log_directory` filter is left alone.
     */
    private static function deleteLogFiles(): void
    {
        $suffix = get_option(LoggerStorage::STORAGE_FILE_DIRECTORY_OPTION);

        if (! is_string($suffix) || preg_match('/^[a-z0-9]{32}$/', $suffix) !== 1) {
            return;
        }

        $uploads = wp_upload_dir(null, false);
        $directory = trailingslashit($uploads['basedir']) . 'buckaroo-logs-' . $suffix . '/';

        if (! is_dir($directory)) {
            return;
        }

        foreach ((array) scandir($directory) as $fileName) {
            if (is_string($fileName) && is_file($directory . $fileName)) {
                wp_delete_file($directory . $fileName);
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Empty directory created by the plugin.
        @rmdir($directory);
    }

    private static function dropTables(): void
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'woocommerce_buckaroo_transactions',
            $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE,
        ];

        foreach ($tables as $table) {
            // Table names are built from $wpdb->prefix and fixed suffixes.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }

    private static function deleteOptions(): void
    {
        global $wpdb;

        delete_option(Install::DATABASE_VERSION_KEY);

        $patterns = array_map(
            static function (string $prefix) use ($wpdb): string {
                return $wpdb->esc_like($prefix) . '%';
            },
            self::OPTION_PREFIXES
        );
        // Admin notices are stored per user as "<user id>buckarooAdminNotice".
        $patterns[] = $wpdb->esc_like('_transient_') . '%' . $wpdb->esc_like('buckarooAdminNotice');
        $patterns[] = $wpdb->esc_like('_transient_timeout_') . '%' . $wpdb->esc_like('buckarooAdminNotice');

        $where = implode(' OR ', array_fill(0, count($patterns), 'option_name LIKE %s'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholders only.
        $names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE {$where}", $patterns));

        foreach ((array) $names as $name) {
            delete_option($name);
        }
    }
}
