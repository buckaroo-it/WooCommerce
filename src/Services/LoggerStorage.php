<?php

namespace Buckaroo\Woocommerce\Services;

use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

/**
 * Singleton for storing logger data
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
class LoggerStorage
{
    public const STORAGE_FILE = 'file';

    public const STORAGE_DB = 'database';

    public const STORAGE_ALL = 'all';

    public const STORAGE_FILE_LOCATION = 'api/log/';

    public const STORAGE_DB_TABLE = 'buckaroo_log';

    public const REDACTED = '[redacted]';

    private const REDACT_MAX_DEPTH = 32;

    /**
     * Matches a normalized (lowercase, alphanumeric only) key whose value is never logged:
     * credential/token/payment-data fragments, or any "...key" that is not a bare "key"
     * or a transaction reference (transactionkey, datarequestkey, paymentkey)
     */
    private const SENSITIVE_KEY_PATTERN = '/secret|password|passwd|token|nonce|authorization|phpauthpw|cookie|iban|carddata|cardnumber|cvc|cvv|encrypted|(?<!^|transaction|datarequest|payment)key$/';

    public static $storageList = [
        self::STORAGE_ALL,
        self::STORAGE_FILE,
        self::STORAGE_DB,
    ];

    public static function getStorage()
    {
        return Helper::get('logstorage') ?? self::STORAGE_FILE;
    }

    /**
     * \Buckaroo\Woocommerce\Services\LoggerStorage Singleton
     *
     * @var self
     */
    private static $instance;

    /**
     * Unique id for current instance
     *
     * @var string
     */
    private static $processId;

    /**
     * Private construct
     */
    private function __construct()
    {
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function downloadFile($name)
    {
        $file = self::get_file_storage_location() . $name;

        if (file_exists($file)) {
            header('Expires: 0');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Cache-Control: pre-check=0, post-check=0, max-age=0', false);
            header('Pragma: no-cache');
            header('Content-type: text/plain');
            header("Content-Disposition:attachment; filename={$name}");

            readfile($file);
            exit();
        } else {
            echo '<p>No log file found</p>';
            exit();
        }
    }

    /**
     * Log into into storage
     *
     * @param  mixed  $message
     * @param  string|null  $method
     * @return void
     */
    public function log(string $locationId, $message)
    {
        if (Helper::get('debugmode') != 'on') {
            return;
        }

        $message = $this->format_message(self::redact($message));
        $storage = static::getStorage();
        $method = $this->get_method_name($storage);

        $date = date('Y-m-d h:i:s');

        if (method_exists($this, $method)) {
            $this->{$method}(
                [$date, $this->getProcessId(), $locationId, $message]
            );
        }
    }

    /**
     * Replace values stored under credential, token or payment-data keys
     * so they never reach the log storage
     *
     * @param  mixed  $data
     * @return mixed
     */
    public static function redact($data, int $depth = 0)
    {
        if ((is_array($data) || is_object($data)) && $depth >= self::REDACT_MAX_DEPTH) {
            return self::REDACTED;
        }

        if ($data instanceof \stdClass) {
            return (object) self::redact((array) $data, $depth);
        }

        if ($data instanceof ResponseParser) {
            // Formatted items include values changed later through set()
            return [
                'class' => get_class($data),
                'items' => self::redact($data->get(), $depth + 1),
                'unformattedItems' => self::redact($data->get(null, null, false), $depth + 1),
            ];
        }

        if ($data instanceof \Throwable) {
            return self::format_throwable($data);
        }

        if (! is_array($data)) {
            return $data;
        }

        $parameterName = $data['Name'] ?? $data['name'] ?? null;
        $redactParameterValue = is_string($parameterName) && self::is_sensitive_key($parameterName);

        foreach ($data as $key => $value) {
            if (is_string($key) && (self::is_sensitive_key($key) || ($redactParameterValue && strtolower($key) === 'value'))) {
                $data[$key] = self::REDACTED;
            } else {
                $data[$key] = self::redact($value, $depth + 1);
            }
        }

        return $data;
    }

    /**
     * Describe a throwable with its trace but without call arguments,
     * which can hold credentials or payment data
     *
     * @return string
     */
    protected static function format_throwable(\Throwable $throwable)
    {
        $lines = [];
        $prefix = '';

        do {
            $lines[] = sprintf(
                '%s%s: %s in %s:%d',
                $prefix,
                get_class($throwable),
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine()
            );

            foreach ($throwable->getTrace() as $index => $frame) {
                $lines[] = sprintf(
                    '#%d %s(%s): %s%s%s()',
                    $index,
                    $frame['file'] ?? '[internal function]',
                    $frame['line'] ?? '',
                    $frame['class'] ?? '',
                    $frame['type'] ?? '',
                    $frame['function'] ?? ''
                );
            }

            $prefix = 'Caused by: ';
            $throwable = $throwable->getPrevious();
        } while ($throwable !== null);

        return implode(PHP_EOL, $lines);
    }

    /**
     * Request details that are safe to log, in place of a full $_SERVER dump
     * (which also carries auth headers, cookies, query strings and environment variables)
     *
     * @return array
     */
    public static function request_context()
    {
        $fields = ['REQUEST_METHOD', 'CONTENT_TYPE', 'CONTENT_LENGTH', 'REMOTE_ADDR', 'HTTP_USER_AGENT'];
        $context = [];

        if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
            $context['REQUEST_PATH'] = strtok($_SERVER['REQUEST_URI'], '?');
        }

        foreach ($fields as $field) {
            if (isset($_SERVER[$field])) {
                $context[$field] = $_SERVER[$field];
            }
        }

        return $context;
    }

    /**
     * Check if a key holds a credential, token or payment data
     *
     * @return bool
     */
    protected static function is_sensitive_key(string $key)
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $key));

        return preg_match(self::SENSITIVE_KEY_PATTERN, $normalized) === 1;
    }

    /**
     * Format message for storage
     *
     * @param  mixed  $message
     * @return string
     */
    protected function format_message($message)
    {
        if (is_object($message) || is_array($message)) {
            return var_export($message, true);
        }

        return $message;
    }

    /**
     * Get method to handle the storing
     *
     * @param  string  $storage
     * @return string
     */
    protected function get_method_name($storage)
    {
        if (! in_array($storage, static::$storageList)) {
            $storage = self::STORAGE_FILE;
        }

        return 'store_in_' . $storage;
    }

    protected static function getProcessId()
    {
        if (empty(self::$processId)) {
            self::$processId = uniqid('', true);
        }

        return self::$processId;
    }

    /**
     * Store in database
     *
     *
     * @return void
     */
    public function store_in_database(array $info)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::STORAGE_DB_TABLE;

        [$date, $processId, $locationId, $message] = $info;

        $data = [
            'date' => $date,
            'process_id' => $processId,
            'message' => $message,
            'location_id' => $locationId,
        ];

        $format = ['%s', '%s', '%s'];
        $wpdb->insert(
            $table,
            $data,
            $format
        );
    }

    /**
     * Store in all storage mediums
     *
     *
     * @return void
     */
    protected function store_in_all(array $info)
    {
        $storageList = array_diff(static::$storageList, ['all']);
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
     *
     * @return void
     */
    protected function store_in_file(array $info)
    {
        @file_put_contents(
            self::get_file_storage_location() . date('d-m-Y') . '.log',
            implode(
                '|||',
                $info
            ) . PHP_EOL,
            FILE_APPEND
        );
    }

    public static function get_file_storage_location()
    {
        return plugin_dir_path(BK_PLUGIN_FILE) . self::STORAGE_FILE_LOCATION;
    }
}
