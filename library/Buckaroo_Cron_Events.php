<?php

require_once 'config.php';
/**
 * Core class for logging
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */
if (!class_exists('Buckaroo__Cron_Events'))
{
    
    class Buckaroo_Cron_Events
    {
        /**
         * Number of days to keep the logs
         */
        const LOG_STALE_DAYS = 14;

        public function __construct()
        {

            add_action(
                'buckaroo_clean_logger_storage', [$this, 'clean_logger_storage']
            );

            if (!wp_next_scheduled('buckaroo_clean_logger_storage')) {
                wp_schedule_event(
                    time(),
                    'daily',
                    'buckaroo_clean_logger_storage'
                );
            }
        }

        /**
         * Hook function for clearing stale logs
         *
         * @return void
         */
        public function clean_logger_storage()
        {
            $storage = BuckarooConfig::get('logstorage') ?? Buckaroo_Logger_Storage::STORAGE_ALL;
            $method = $this->get_logger_clean_method_name($storage);
            if (method_exists($this, $method)) {
                $this->{$method}();
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
        protected function get_logger_clean_method_name($storage)
        {
            if (strlen($storage) === 0) {
                $storage = Buckaroo_Logger_Storage::STORAGE_ALL;
            }
            
            return 'clean_logger_storage_'.$storage;
        }
        /**
         * Clean all logger storage mediums
         *
         * @return void
         */
        protected function clean_logger_storage_all()
        {
            $storageList = array_diff(Buckaroo_Logger_Storage::$storageList, ['all']);
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
            $name = Buckaroo_Logger_Storage::get_file_location();
            if (!file_exists($name)) {
                return;
            }

            try {
                $lineCount = $this->get_start_stale_line($name);
                $file = new \SplFileObject($name, 'r');
                
                $tempName = $name.".temp.text";
                $temp = new SplFileObject($tempName, "w+");
                $temp->flock(LOCK_EX);
                $file->rewind();
                $file->seek($lineCount);

                while (!$file->eof()) {
                    $temp->fwrite($file->current());
                    $file->next();
                }
                $temp->flock(LOCK_UN);
                
                unset($file, $temp);
                unlink($name);
                rename($tempName, $name);

            } catch (\Throwable $th) {
                Buckaroo_Logger::log($th->getMessage());
            }

        }
        public function get_start_stale_line($name)
        {
            $file = new \SplFileObject($name, 'r');
            $file->setFlags(SplFileObject::DROP_NEW_LINE);
            $file->seek(PHP_INT_MAX);
            $lineCount = $file->key();
            $notStale = true;

            
            while ($lineCount > 0 && $notStale) {
                $file->seek($lineCount);
                $notStale = $this->is_file_line_not_stale(
                    $this->get_date_from_line(
                        $file->current()
                    )
                );
                if ($notStale) {
                    $lineCount = $lineCount - 1;    
                }
            }
            
            return $lineCount - 1;
        }
        /**
         * Attempt to get a date from log file
         *
         * @param string $line
         *
         * @return \DateTime|null
         */
        protected function get_date_from_line($line)
        {
            $tmp = explode("|||", $line);
            try {
                return new DateTime($tmp[0]);
            } catch (\Throwable $th) {
                Buckaroo_Logger::log("Unkown date format");
            }
        }
        /**
         * Check if line is older than required
         *
         * @param \DateTime|null $date
         *
         * @return boolean
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
         * Get stale date
         *
         * @return \DateTime
         */
        protected function get_stale_date()
        {
           return (new \DateTime())
            ->sub(new \DateInterval("P".self::LOG_STALE_DAYS."D"));
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

            $table = $wpdb->prefix.Buckaroo_Logger_Storage::STORAGE_DB_TABLE;
            $staleDate = $this->get_stale_date()->format('Y-m-d H:i:s');
            $wpdb->query(
                "DELETE FROM `". $table . "` WHERE `date` < '".$staleDate."'"
            );
        }
    }
}