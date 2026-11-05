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

    /**
     * Old log folder inside the plugin, which the web server serves to anyone.
     * Files found there are moved to the protected folder.
     */
    public const LEGACY_FILE_LOCATION = 'api/log/';

    /** Log folder inside wp-content/uploads */
    public const FILE_DIRECTORY = 'buckaroo-logs';

    /** Log files are named <d-m-Y>-<hash>.log */
    public const LOG_DATE_FORMAT = 'd-m-Y';

    /** @var bool */
    private static $fileStoragePrepared = false;

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

    /**
     * Send the log file of one day as a download. Callers check the user's
     * permissions first.
     *
     * @param  string  $date  d-m-Y
     */
    public static function downloadFile($date)
    {
        $file = self::get_log_file_path((string) $date);

        if ($file === null) {
            wp_die(esc_html__('No log file found', 'wc-buckaroo-bpe-gateway'), '', ['response' => 404]);
        }

        // @codeCoverageIgnoreStart
        // Ends the request, which a test cannot do
        self::send_log_file($file, (string) $date);
        exit;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Headers and contents of a log file download
     *
     * @param  string  $date  d-m-Y
     */
    protected static function send_log_file(string $file, string $date): void
    {
        if (! headers_sent()) {
            // @codeCoverageIgnoreStart
            // Tests have printed output already, so headers cannot be sent there
            nocache_headers();
            foreach (self::download_headers($date) as $header) {
                header($header);
            }
            // @codeCoverageIgnoreEnd
        }

        readfile($file);
    }

    /**
     * @param  string  $date  d-m-Y
     * @return string[]
     */
    protected static function download_headers(string $date): array
    {
        return [
            'Content-Type: text/plain; charset=utf-8',
            'Content-Disposition: attachment; filename="buckaroo-' . $date . '.log"',
            'X-Content-Type-Options: nosniff',
        ];
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

        // 24-hour clock; "h" made 1 pm and 1 am look the same
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
        if (! self::prepare_file_storage()) {
            return;
        }

        @file_put_contents(
            self::get_file_storage_location() . self::get_log_file_name(date(self::LOG_DATE_FORMAT)),
            implode(
                '|||',
                $info
            ) . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * Folder that holds the log files, in wp-content/uploads so plugin updates keep it
     */
    public static function get_file_storage_location()
    {
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir(null, false) : [];
        $baseDir = ! empty($uploads['basedir']) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';

        return trailingslashit($baseDir) . self::FILE_DIRECTORY . '/';
    }

    /**
     * File name for the log of a day. The hash, made with the site's secret salts,
     * keeps the name unguessable on servers that ignore the folder's .htaccess (nginx).
     *
     * @param  string  $date  d-m-Y
     */
    public static function get_log_file_name(string $date): string
    {
        return $date . '-' . wp_hash('buckaroo-log-' . $date) . '.log';
    }

    /**
     * Path of the log file of a day, or null when the date is invalid or there is no log
     *
     * @param  string  $date  d-m-Y
     */
    public static function get_log_file_path(string $date): ?string
    {
        if (! self::is_log_date($date)) {
            return null;
        }

        $file = self::get_file_storage_location() . self::get_log_file_name($date);

        return is_file($file) ? $file : null;
    }

    /**
     * Log files by date (d-m-Y), newest first
     *
     * @return array<string, string>
     */
    public static function get_log_files(): array
    {
        self::prepare_file_storage();

        $files = [];
        foreach (glob(self::get_file_storage_location() . '*.log') ?: [] as $file) {
            $date = substr(basename($file), 0, 10);
            if (self::is_log_date($date) && basename($file) === self::get_log_file_name($date)) {
                $files[$date] = $file;
            }
        }

        uksort(
            $files,
            static function ($a, $b) {
                return \DateTime::createFromFormat('!' . self::LOG_DATE_FORMAT, $b) <=> \DateTime::createFromFormat('!' . self::LOG_DATE_FORMAT, $a);
            }
        );

        return $files;
    }

    public static function is_log_date(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('!' . self::LOG_DATE_FORMAT, $date);

        return $parsed !== false && $parsed->format(self::LOG_DATE_FORMAT) === $date;
    }

    /**
     * Create the log folder, block web access to it and move logs out of the old
     * public folder in the plugin. Runs once per request.
     */
    public static function prepare_file_storage(): bool
    {
        $directory = self::get_file_storage_location();

        if (self::$fileStoragePrepared) {
            return is_dir($directory);
        }
        self::$fileStoragePrepared = true;

        if (! is_dir($directory) && ! wp_mkdir_p($directory)) {
            return false;
        }

        // Apache honours .htaccess; the index file stops directory listings elsewhere
        $protection = [
            '.htaccess' => "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n",
            'index.html' => '',
            'index.php' => "<?php\n// Silence is golden.\n",
        ];
        foreach ($protection as $name => $contents) {
            if (! file_exists($directory . $name)) {
                @file_put_contents($directory . $name, $contents);
            }
        }

        self::move_legacy_log_files($directory);

        return true;
    }

    /**
     * Older versions wrote <d-m-Y>.log into the plugin folder, where anyone can open them
     */
    protected static function move_legacy_log_files(string $directory): void
    {
        $legacyDirectory = plugin_dir_path(BK_PLUGIN_FILE) . self::LEGACY_FILE_LOCATION;

        foreach (glob($legacyDirectory . '*.log') ?: [] as $legacyFile) {
            $date = basename($legacyFile, '.log');
            if (! self::is_log_date($date)) {
                @unlink($legacyFile);
                continue;
            }

            $target = $directory . self::get_log_file_name($date);
            if (file_exists($target)) {
                // Keep both: the old entries go in front of the new ones
                $contents = @file_get_contents($legacyFile);
                if ($contents !== false && @file_put_contents($target, $contents . @file_get_contents($target)) !== false) {
                    @unlink($legacyFile);
                }
            } elseif (! @rename($legacyFile, $target)) {
                @unlink($legacyFile);
            }
        }
    }
}
