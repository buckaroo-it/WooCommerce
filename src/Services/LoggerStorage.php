<?php

namespace Buckaroo\Woocommerce\Services;

use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use DateTime;

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

    /** Option holding the random part of the log directory name */
    public const STORAGE_FILE_DIRECTORY_OPTION = 'buckaroo_log_directory_suffix';

    /** Daily log file name: dd-mm-yyyy.log */
    public const LOG_FILE_NAME_PATTERN = '/^\d{2}-\d{2}-\d{4}\.log$/';

    private const LOG_FILE_DATE_FORMAT = 'd-m-Y';

    private const HTACCESS_DENY_ALL = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order Allow,Deny\n    Deny from all\n</IfModule>\n";

    public const STORAGE_DB_TABLE = 'buckaroo_log';

    public const REDACTED = '[redacted]';

    private const REDACT_MAX_DEPTH = 32;

    /**
     * Matches a normalized (lowercase, alphanumeric only) key whose value is never logged:
     * credential/token/payment-data fragments, or any "...key" that is not a bare "key"
     * or a transaction reference (transactionkey, datarequestkey, paymentkey)
     */
    private const SENSITIVE_KEY_PATTERN = '/secret|password|passwd|token|nonce|authorization|phpauthpw|cookie|iban|carddata|cardnumber|cvc|cvv|encrypted|signature|paymentdata|sessionid|(?<!^|transaction|datarequest|payment)key$/';

    /**
     * Matches a normalized key that holds customer details: names, contact details,
     * addresses, dates of birth, company registration numbers and IP addresses
     */
    private const PERSONAL_DATA_KEY_PATTERN = '/firstname|lastname|givenname|familyname|fullname|middlename|initials|(consumer|customer|account|holder|payer|recipient|contact|card)name|careof|email|phone|mobile|street|housenumber|housenr|address|postcode|postalcode|zipcode|^zip$|city$|locality|birth|dob$|chamberofcommerce|coc|kvk|consumerbin|^bin$|^ip$|clientip|remoteaddr|forwardedfor/';

    /** Matches a normalized key whose "name" entry is the name of a person */
    private const PERSON_CONTAINER_PATTERN = '/customer|recipient|consumer|contact|payer|holder|person|billing|shipping/';

    /** Query parameters in a logged string whose value is replaced */
    private const URL_PARAMETER_PATTERN = '/([?&;]|^)([A-Za-z0-9_\-\[\]%.]+)=([^&#\s\'"]*)/';

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
     * Whether a failed log file write was already reported in this request
     *
     * @var bool
     */
    private static $fileWriteFailureReported = false;

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

    /**
     * Date of a daily log file, or null when the name is not a daily log file
     * name for an existing calendar date
     *
     * @param  mixed  $fileName
     * @return DateTime|null
     */
    public static function get_log_file_date($fileName)
    {
        if (! is_string($fileName) || preg_match(self::LOG_FILE_NAME_PATTERN, $fileName) !== 1) {
            return null;
        }

        $dateString = substr($fileName, 0, -4);
        $date = DateTime::createFromFormat('!' . self::LOG_FILE_DATE_FORMAT, $dateString);

        if (! $date instanceof DateTime || $date->format(self::LOG_FILE_DATE_FORMAT) !== $dateString) {
            return null;
        }

        return $date;
    }

    /**
     * Daily log files in the log directory, keyed by file name
     *
     * @return DateTime[]
     */
    public static function get_log_files()
    {
        $directory = self::get_file_storage_location();

        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach ((array) scandir($directory) as $fileName) {
            $date = self::get_log_file_date($fileName);

            if ($date !== null && is_file($directory . $fileName)) {
                $files[$fileName] = $date;
            }
        }

        return $files;
    }

    /**
     * Full path of an existing daily log file, or null when the name is not
     * a daily log file name or the file does not exist
     *
     * @param  mixed  $fileName
     * @return string|null
     */
    public static function get_log_file_path($fileName)
    {
        if (self::get_log_file_date($fileName) === null) {
            return null;
        }

        $file = self::get_file_storage_location() . $fileName;

        return is_file($file) ? $file : null;
    }

    /**
     * Send a log file to the browser as a download
     *
     * @return void
     */
    public static function send_file(string $file)
    {
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('X-Content-Type-Options: nosniff');

        readfile($file);
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

        $locationId = self::redact_string($locationId);
        $message = $this->format_message(self::redact($message));
        $storage = static::getStorage();
        $method = $this->get_method_name($storage);

        $date = date('Y-m-d H:i:s');

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
     * @param  string  $parentKey  Key the data is stored under
     * @return mixed
     */
    public static function redact($data, int $depth = 0, string $parentKey = '')
    {
        if ((is_array($data) || is_object($data)) && $depth >= self::REDACT_MAX_DEPTH) {
            return self::REDACTED;
        }

        if ($data instanceof \stdClass) {
            return (object) self::redact((array) $data, $depth, $parentKey);
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

        if (is_object($data)) {
            return [
                'class' => get_class($data),
                'properties' => self::redact(self::object_properties($data), $depth + 1),
            ];
        }

        if (is_string($data)) {
            return self::redact_string($data, $depth);
        }

        if (! is_array($data)) {
            return $data;
        }

        $parameterName = $data['Name'] ?? $data['name'] ?? null;
        $redactParameterValue = is_string($parameterName) && self::is_sensitive_key($parameterName);
        $isPersonContainer = preg_match(self::PERSON_CONTAINER_PATTERN, strtolower(preg_replace('/[^a-z0-9]/i', '', $parentKey))) === 1;

        foreach ($data as $key => $value) {
            $redactValue = is_string($key) && (self::is_sensitive_key($key)
                || ($redactParameterValue && strtolower($key) === 'value')
                || ($isPersonContainer && strtolower($key) === 'name'));

            if ($redactValue) {
                $data[$key] = self::REDACTED;
            } else {
                $data[$key] = self::redact($value, $depth + 1, (string) $key);
            }
        }

        return $data;
    }

    /**
     * Apply the key rules to a string: JSON in the string is decoded and redacted,
     * query parameters are redacted by name, and order keys and email
     * addresses are replaced wherever they appear
     *
     * @return string
     */
    protected static function redact_string(string $value, int $depth = 0)
    {
        if (strpbrk($value, '{[') !== false) {
            $value = self::redact_json_fragments($value, $depth);
        }

        if (strpos($value, '=') !== false) {
            $value = preg_replace_callback(
                self::URL_PARAMETER_PATTERN,
                function ($match) {
                    $name = urldecode($match[2]);

                    if (strtolower($name) !== 'key' && ! self::is_sensitive_key($name)) {
                        return $match[0];
                    }

                    return $match[1] . $match[2] . '=' . self::REDACTED;
                },
                $value
            ) ?? self::REDACTED;
        }

        return preg_replace(
            ['/wc_order_[A-Za-z0-9]+/', '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/'],
            self::REDACTED,
            $value
        ) ?? self::REDACTED;
    }

    /**
     * Redact every JSON object or array found in a string
     *
     * @return string
     */
    protected static function redact_json_fragments(string $value, int $depth)
    {
        $result = '';
        $offset = 0;
        $length = strlen($value);

        while (($start = $offset + strcspn($value, '{[', $offset)) < $length) {
            $end = self::json_fragment_end($value, $start);
            $decoded = $end === null ? null : json_decode(substr($value, $start, $end - $start + 1), true);

            if (! is_array($decoded)) {
                $result .= substr($value, $offset, $start - $offset + 1);
                $offset = $start + 1;
                continue;
            }

            $encoded = wp_json_encode(self::redact($decoded, $depth + 1), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $result .= substr($value, $offset, $start - $offset) . (is_string($encoded) ? $encoded : self::REDACTED);
            $offset = $end + 1;
        }

        return $result . substr($value, $offset);
    }

    /**
     * Position of the bracket that closes the one at $start, skipping
     * brackets inside JSON strings
     *
     * @return int|null
     */
    protected static function json_fragment_end(string $value, int $start)
    {
        $level = 0;
        $inString = false;
        $length = strlen($value);

        for ($i = $start; $i < $length; $i++) {
            $char = $value[$i];

            if ($inString) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $inString = false;
                }
            } elseif ($char === '"') {
                $inString = true;
            } elseif ($char === '{' || $char === '[') {
                $level++;
            } elseif (($char === '}' || $char === ']') && --$level === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Properties of an object, including protected and private ones,
     * keyed by their plain property name
     *
     * @return array
     */
    protected static function object_properties($object)
    {
        $properties = [];

        foreach ((array) $object as $name => $value) {
            $name = (string) $name;
            $separator = strrpos($name, "\0");
            $properties[$separator === false ? $name : substr($name, $separator + 1)] = $value;
        }

        return $properties;
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
                self::redact_string($throwable->getMessage()),
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
        $fields = ['REQUEST_METHOD', 'CONTENT_TYPE', 'CONTENT_LENGTH', 'HTTP_USER_AGENT'];
        $context = [];

        if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
            $context['REQUEST_PATH'] = strtok(wp_strip_all_tags(wp_unslash($_SERVER['REQUEST_URI'])), '?');
        }

        foreach ($fields as $field) {
            if (isset($_SERVER[$field]) && is_scalar($_SERVER[$field])) {
                $context[$field] = sanitize_text_field(wp_unslash((string) $_SERVER[$field]));
            }
        }

        return $context;
    }

    /**
     * Check if a key holds a credential, token, payment data or customer details
     *
     * @return bool
     */
    protected static function is_sensitive_key(string $key)
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $key));

        return preg_match(self::SENSITIVE_KEY_PATTERN, $normalized) === 1
            || preg_match(self::PERSONAL_DATA_KEY_PATTERN, $normalized) === 1;
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
        $directory = self::get_file_storage_location();

        if (! self::prepare_file_storage_location($directory)) {
            self::report_file_write_failure();

            return;
        }

        $file = $directory . date(self::LOG_FILE_DATE_FORMAT) . '.log';

        if (file_exists($file) && ! is_writable($file)) {
            self::report_file_write_failure();

            return;
        }

        $written = file_put_contents(
            $file,
            implode(
                '|||',
                $info
            ) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($written === false) {
            self::report_file_write_failure();
        }
    }

    /**
     * Directory of the daily log files: a directory with a random name,
     * created once per site, inside the uploads directory
     *
     * @return string
     */
    public static function get_file_storage_location()
    {
        $suffix = get_option(self::STORAGE_FILE_DIRECTORY_OPTION);

        if (! is_string($suffix) || preg_match('/^[a-z0-9]{32}$/', $suffix) !== 1) {
            $suffix = strtolower(wp_generate_password(32, false));
            update_option(self::STORAGE_FILE_DIRECTORY_OPTION, $suffix);
        }

        $uploads = wp_upload_dir(null, false);
        $directory = trailingslashit($uploads['basedir']) . 'buckaroo-logs-' . $suffix;

        /**
         * Directory where the Buckaroo debug log files are stored,
         * for example a directory outside the web root
         *
         * @param  string  $directory
         */
        $directory = apply_filters('buckaroo_log_directory', $directory);

        return trailingslashit($directory);
    }

    /**
     * Create the log directory together with files that deny web access to it
     *
     * @return bool Whether the directory exists and is writable
     */
    protected static function prepare_file_storage_location(string $directory)
    {
        if (! is_dir($directory) && ! wp_mkdir_p($directory)) {
            return false;
        }

        if (! is_writable($directory)) {
            return false;
        }

        $accessFiles = [
            '.htaccess' => self::HTACCESS_DENY_ALL,
            'index.html' => '',
            'index.php' => "<?php\n// Silence is golden.\n",
        ];

        foreach ($accessFiles as $name => $content) {
            if (! file_exists($directory . $name)) {
                file_put_contents($directory . $name, $content);
            }
        }

        return true;
    }

    /**
     * Note once per request that a log line could not be written
     *
     * @return void
     */
    protected static function report_file_write_failure()
    {
        if (self::$fileWriteFailureReported) {
            return;
        }

        self::$fileWriteFailureReported = true;

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->warning(
                'The Buckaroo debug log could not be written to the log directory.',
                ['source' => 'buckaroo']
            );
        }
    }
}
