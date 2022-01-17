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
if (!class_exists('Buckaroo_Logger_Storage'))
{
    class Buckaroo_Logger
    {
        /**
         * Log into into storage
         *
         * @param mixed $message
         * @param string $locationId
         * @param string|null $method
         *
         * @return void
         */
        public static function log($locationId, $message = null)
        {   
            if ($message === null) {
                $message = $locationId;
                $locationId = '';
            }
            $loggerStorage = Buckaroo_Logger_Storage::get_instance();
            $loggerStorage->log($locationId, $message);
        }
    }
};
?>
