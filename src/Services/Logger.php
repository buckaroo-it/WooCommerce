<?php

namespace Buckaroo\Woocommerce\Services;

/**
 * Core class for logging
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 2.25.0
 *
 * @link      https://www.buckaroo.eu/
 */
class Logger
{
    /**
     * Log into into storage
     *
     * @param  string  $locationId
     * @param  mixed  $message
     * @return void
     */
    public static function log($locationId, $message = null)
    {
        if ($message === null) {
            $message = $locationId;
            $locationId = '';
        }
        $loggerStorage = LoggerStorage::get_instance();
        $loggerStorage->log($locationId, $message);
    }
}
