<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Services\LoggerStorage;
use DateInterval;
use DateTime;

/**
 * Start running buckaroo events
 */
class CronEvents
{
    /**
     * Number of days to keep the logs
     */
    public const LOG_STALE_DAYS = 14;

    public function __construct()
    {
        add_action(
            'buckaroo_clean_logger_storage',
            [$this, 'clean_logger_storage']
        );
        add_action('init', [$this, 'schedule']);
    }

    /**
     * Schedule the daily log cleanup.
     *
     * Runs on init: scheduling reads the cron schedules, which WooCommerce
     * labels with translated strings, and WordPress reports translations
     * loaded before init as loaded too early.
     *
     * @return void
     */
    public function schedule()
    {
        if (! wp_next_scheduled('buckaroo_clean_logger_storage')) {
            wp_schedule_event(
                time(),
                'daily',
                'buckaroo_clean_logger_storage'
            );
        }
    }

    /**
     * Unschedule the events
     *
     * @return void
     */
    public static function unschedule()
    {
        $timestamp = wp_next_scheduled('buckaroo_clean_logger_storage');
        wp_unschedule_event($timestamp, 'buckaroo_clean_logger_storage');
    }

    /**
     * Hook function for clearing stale logs
     *
     * @return void
     */
    public function clean_logger_storage()
    {
        $storage = LoggerStorage::getStorage();
        $method = $this->get_logger_clean_method_name($storage);
        if (method_exists($this, $method)) {
            $this->{$method}();
        }
    }

    protected function get_logger_clean_method_name($storage)
    {
        if (strlen($storage) === 0) {
            $storage = LoggerStorage::STORAGE_ALL;
        }

        return 'clean_logger_storage_' . $storage;
    }

    /**
     * Clean all logger storage mediums
     *
     * @return void
     */
    protected function clean_logger_storage_all()
    {
        $storageList = array_diff(LoggerStorage::$storageList, ['all']);
        foreach ($storageList as $storage) {
            $method = $this->get_logger_clean_method_name($storage);
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }
    }

    /**
     * Clean file storage
     *
     * @return void
     */
    protected function clean_logger_storage_file()
    {
        $directory = LoggerStorage::get_file_storage_location();
        $staleDate = (new DateTime('today'))
            ->sub(new DateInterval('P' . self::LOG_STALE_DAYS . 'D'));

        foreach (LoggerStorage::get_log_files() as $fileName => $date) {
            if ($date < $staleDate) {
                unlink($directory . $fileName);
            }
        }
    }

    /**
     * Get stale date
     *
     * @return DateTime
     */
    protected function get_stale_date()
    {
        return (new DateTime())
            ->sub(new DateInterval('P' . self::LOG_STALE_DAYS . 'D'));
    }

    /**
     * Check if line is older than required
     *
     * @param  DateTime|null  $date
     * @return bool
     */
    protected function is_file_line_not_stale($date)
    {
        if ($date === null) {
            return true;
        }

        $staleDate = $this->get_stale_date();

        return $date > $staleDate;
    }

    /**
     * Clean database storage
     *
     * @return void
     */
    protected function clean_logger_storage_database()
    {
        global $wpdb;
        $wpdb->hide_errors();

        $staleDate = $this->get_stale_date()->format('Y-m-d H:i:s');
        $wpdb->query(
            $wpdb->prepare("DELETE FROM {$wpdb->prefix}buckaroo_log WHERE `date` < %s", $staleDate)
        );
    }
}
