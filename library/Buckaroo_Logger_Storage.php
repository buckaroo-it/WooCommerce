<?php

require_once 'config.php';
/**
 * Singleton for storing logger data
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
if (!class_exists('Buckaroo_Logger_Storage'))
{
    class Buckaroo_Logger_Storage
    {

        const STORAGE_FILE = 'file';
        const STORAGE_DB = 'database';
        const STORAGE_ALL = 'all';

        const STORAGE_FILE_LOCATION = '/api/log/report_log.txt';
        const STORAGE_DB_TABLE = 'buckaroo_log';

        public static $storageList = array(
            self::STORAGE_ALL,
            self::STORAGE_FILE,
            self::STORAGE_DB
        );

        /**
         * Buckaroo_Logger_Storage Singleton
         *
         * @var Buckaroo_Logger_Storage
         */
        private static $instance;

        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function get_file_location()
        {
            return dirname(__FILE__).self::STORAGE_FILE_LOCATION;
        }

        /**
         * Private construct
         */
        private function __construct()
        {
        }
        /**
         * Log into into storage
         *
         * @param mixed $message
         * @param string $locationId
         * @param string|null $method
         *
         * @return void
         */
        public function log(string $locationId, $message)
        {   
            if(BuckarooConfig::get('BUCKAROO_DEBUG') != 'on') {
                return;
            }
            
            $message = $this->format_message($message);
            $storage = BuckarooConfig::get('logstorage') ?? self::STORAGE_ALL;
            $method = $this->get_method_name($storage);
            
            $date = date('Y-m-d h:i:s');

            if (method_exists($this, $method)) {
                $this->{$method}(array($date, $message, $locationId));
            }
        }
        /**
         * Store in all storage mediums
         *
         * @param array $info
         *
         * @return void
         */
        protected function store_in_all(array $info)
        {            
            $storageList = array_diff(static::$storageList, array('all'));
            foreach ($storageList as $storage) {

                $method = $this->get_method_name($storage);
                if (method_exists($this, $method)) {
                    $this->{$method}($info);
                }
            }
        }
        /**
         * Store in file
         *
         * @param array $info
         *
         * @return void
         */
        protected function store_in_file(array $info)
        {
            @file_put_contents(
                $this->get_file_location(),
                "-->". implode("|||", $info) . PHP_EOL,
                FILE_APPEND
            );
        }
        /**
         * Store in database
         *
         * @param array $info
         *
         * @return void
         */
        public function store_in_database(array $info)
        {
            global $wpdb;
            $table = $wpdb->prefix.self::STORAGE_DB_TABLE;
            
            list($date, $message, $locationId) = $info;
            
            $data = array(
                "date" => $date,
                "message" => $message,
                "location_id" => $locationId
            );
            
            $format = array('%s','%s', '%s');
            $wpdb->insert(
                $table,
                $data,
                $format
            );
        }
        /**
         * Get method to handle the storing
         *
         * @param string $storage
         *
         * @return string
         */
        protected function get_method_name($storage)
        {
            if (!in_array($storage, static::$storageList)) {
                $storage = self::STORAGE_FILE;
            }
            return 'store_in_'.$storage;
        }
        /**
         * Format message for storage
         *
         * @param mixed $message
         *
         * @return string
         */
        protected function format_message($message)
        {
           
            if (is_object($message) || is_array($message)) {
                return var_export($message, true);
            }
            return $message;
        }
    }
};
?>
