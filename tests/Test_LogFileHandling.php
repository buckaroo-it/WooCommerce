<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Admin\ReportPage;
use Buckaroo\Woocommerce\Hooks\CronEvents;
use Buckaroo\Woocommerce\Hooks\ReportDownload;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\LoggerStorage;

/**
 * Daily log files: storage location, download from the Report page and cleanup.
 */
class Test_LogFileHandling extends WP_UnitTestCase
{
    private const MASTER_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    private $originalMaster;

    private $originalGet;

    /** @var string */
    private $directory;

    /** @var string[] Directories removed after the test */
    private $createdDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalMaster = get_option(self::MASTER_OPTION, null);
        $this->originalGet = $_GET;
        $_GET = [];

        update_option(self::MASTER_OPTION, [
            'debugmode' => 'on',
            'logstorage' => LoggerStorage::STORAGE_FILE,
        ]);

        $this->directory = trailingslashit(get_temp_dir()) . 'buckaroo-log-test-' . wp_generate_password(8, false);
        $this->createdDirectories[] = $this->directory;
        add_filter('buckaroo_log_directory', [$this, 'filterDirectory']);

        $this->resetWriteFailureFlag();
    }

    protected function tearDown(): void
    {
        remove_filter('buckaroo_log_directory', [$this, 'filterDirectory']);
        $_GET = $this->originalGet;

        if ($this->originalMaster === null) {
            delete_option(self::MASTER_OPTION);
        } else {
            update_option(self::MASTER_OPTION, $this->originalMaster);
        }

        foreach ($this->createdDirectories as $directory) {
            $this->removeDirectory($directory);
        }

        $this->resetWriteFailureFlag();
        parent::tearDown();
    }

    public function filterDirectory(): string
    {
        return $this->directory;
    }

    // Download

    public function test_download_is_only_handled_by_the_logged_in_admin_post_action(): void
    {
        remove_all_actions('admin_post_' . ReportDownload::ACTION);
        new ReportDownload();

        $this->assertNotFalse(has_action('admin_post_' . ReportDownload::ACTION));
        $this->assertFalse(has_action('admin_post_nopriv_' . ReportDownload::ACTION));
    }

    public function test_download_parameter_on_a_regular_request_does_not_output_the_file(): void
    {
        $fileName = $this->createLogFile('-1 day', 'LOG-CONTENT-FIXTURE');
        $_GET['buckaroo_download_log_file'] = $fileName;

        ob_start();
        new ReportDownload();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_logged_out_request_is_rejected(): void
    {
        $fileName = $this->createLogFile('-1 day');
        wp_set_current_user(0);
        $this->setRequest($fileName, wp_create_nonce(ReportDownload::ACTION));

        $this->assertErrorStatus(403, (new ReportDownload())->get_requested_file());
    }

    public function test_logged_out_download_stops_with_status_403_and_no_content(): void
    {
        $fileName = $this->createLogFile('-1 day', 'LOG-CONTENT-FIXTURE');
        wp_set_current_user(0);
        $this->setRequest($fileName, 'invalid');

        ob_start();
        try {
            (new ReportDownload())->download();
            $this->fail('The download did not stop');
        } catch (WPDieException $exception) {
            $this->assertSame(403, $exception->getCode());
        } finally {
            $output = ob_get_clean();
        }

        $this->assertStringNotContainsString('LOG-CONTENT-FIXTURE', $output);
    }

    public function test_user_without_manage_woocommerce_gets_status_403(): void
    {
        $fileName = $this->createLogFile('-1 day');
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));
        $this->setRequest($fileName, wp_create_nonce(ReportDownload::ACTION));

        $this->assertErrorStatus(403, (new ReportDownload())->get_requested_file());
    }

    public function test_manager_with_missing_nonce_is_rejected(): void
    {
        $fileName = $this->createLogFile('-1 day');
        wp_set_current_user(self::factory()->user->create(['role' => 'shop_manager']));
        $this->setRequest($fileName, null);

        $this->assertErrorStatus(403, (new ReportDownload())->get_requested_file());
    }

    public function test_manager_with_invalid_nonce_is_rejected(): void
    {
        $fileName = $this->createLogFile('-1 day');
        wp_set_current_user(self::factory()->user->create(['role' => 'shop_manager']));
        $this->setRequest($fileName, wp_create_nonce('another-action'));

        $this->assertErrorStatus(403, (new ReportDownload())->get_requested_file());
    }

    public function test_manager_with_valid_nonce_gets_the_log_file(): void
    {
        $fileName = $this->createLogFile('-1 day', 'LOG-CONTENT-FIXTURE');
        wp_set_current_user(self::factory()->user->create(['role' => 'shop_manager']));
        $this->setRequest($fileName, wp_create_nonce(ReportDownload::ACTION));

        $file = (new ReportDownload())->get_requested_file();

        $this->assertIsString($file);
        $this->assertSame(realpath($this->directory . '/' . $fileName), realpath($file));
        $this->assertStringContainsString('LOG-CONTENT-FIXTURE', file_get_contents($file));
    }

    public function test_missing_log_file_gets_status_404(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->setRequest(gmdate('d-m-Y', strtotime('-3 days')) . '.log', wp_create_nonce(ReportDownload::ACTION));

        $this->assertErrorStatus(404, (new ReportDownload())->get_requested_file());
    }

    /**
     * @dataProvider invalidFileNames
     */
    public function test_file_names_outside_the_daily_log_format_are_rejected($fileName): void
    {
        $this->createLogFile('-1 day');
        file_put_contents($this->directory . '/debug.log', 'OTHER-FILE');
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->setRequest($fileName, wp_create_nonce(ReportDownload::ACTION));

        $this->assertErrorStatus(400, (new ReportDownload())->get_requested_file());
    }

    public function invalidFileNames(): array
    {
        $yesterday = gmdate('d-m-Y', strtotime('-1 day')) . '.log';

        return [
            'parent directory' => ['../' . $yesterday],
            'nested parent directory' => ['../../wp-config.php'],
            'encoded parent directory' => ['..%2F' . $yesterday],
            'absolute path' => ['/etc/passwd'],
            'path after name' => [$yesterday . '/../debug.log'],
            'null byte' => [$yesterday . "\0"],
            'trailing newline' => [$yesterday . "\n"],
            'other log file' => ['debug.log'],
            'other extension' => [$yesterday . '.php'],
            'upper case extension' => [strtoupper($yesterday)],
            'single digit day' => ['1-01-2026.log'],
            'year first' => ['2026-01-01.log'],
            'not a calendar date' => ['31-02-2026.log'],
            'month 13' => ['01-13-2026.log'],
            'zero date' => ['00-00-0000.log'],
            'empty' => [''],
            'array' => [[$yesterday]],
        ];
    }

    public function test_download_url_uses_admin_post_with_a_valid_nonce(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $url = ReportDownload::get_download_url('01-02-2026.log');

        $this->assertStringStartsWith(admin_url('admin-post.php'), $url);

        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(ReportDownload::ACTION, $query['action']);
        $this->assertSame('01-02-2026.log', $query[ReportDownload::FILE_PARAMETER]);
        $this->assertNotFalse(wp_verify_nonce($query['_wpnonce'], ReportDownload::ACTION));
    }

    public function test_report_page_links_to_the_admin_download_url(): void
    {
        $fileName = $this->createLogFile('-1 day', 'LOG-CONTENT-FIXTURE');
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        ob_start();
        (new ReportPage())->display_log_file($fileName);
        $output = ob_get_clean();

        $this->assertStringContainsString(esc_url(ReportDownload::get_download_url($fileName)), $output);
        $this->assertStringNotContainsString('buckaroo_download_log_file', $output);
        $this->assertStringNotContainsString(plugin_dir_url(BK_PLUGIN_FILE), $output);
        $this->assertStringContainsString('LOG-CONTENT-FIXTURE', $output);
    }

    public function test_report_page_lists_only_daily_log_files_newest_first(): void
    {
        $older = $this->createLogFile('-5 days');
        $newer = $this->createLogFile('-1 day');
        file_put_contents($this->directory . '/debug.log', 'OTHER-FILE');

        $page = new ReportPage();
        $items = $page->get_items(1);

        $this->assertSame(2, $page->get_total_count_file());
        $this->assertCount(2, $items);
        $this->assertStringContainsString($newer, $items[0]['description']);
        $this->assertStringContainsString($older, $items[1]['description']);
        $this->assertStringContainsString('section=report&#038;log_file=' . $newer, $items[0]['description']);
    }

    // File logging

    public function test_default_log_directory_is_a_random_directory_in_uploads(): void
    {
        remove_filter('buckaroo_log_directory', [$this, 'filterDirectory']);
        delete_option(LoggerStorage::STORAGE_FILE_DIRECTORY_OPTION);

        $directory = LoggerStorage::get_file_storage_location();
        $this->createdDirectories[] = $directory;
        $uploads = wp_upload_dir(null, false);

        $this->assertStringStartsWith(trailingslashit($uploads['basedir']), $directory);
        $this->assertMatchesRegularExpression('#/buckaroo-logs-[a-z0-9]{32}/$#', $directory);
        $this->assertSame($directory, LoggerStorage::get_file_storage_location());
        $this->assertStringNotContainsString(plugin_dir_path(BK_PLUGIN_FILE), $directory);

        delete_option(LoggerStorage::STORAGE_FILE_DIRECTORY_OPTION);
        $this->assertNotSame($directory, LoggerStorage::get_file_storage_location());
    }

    public function test_logging_creates_the_directory_and_writes_the_entry(): void
    {
        $this->assertDirectoryDoesNotExist($this->directory);

        Logger::log('test|location|', 'ENTRY-FIXTURE');

        $file = $this->directory . '/' . date('d-m-Y') . '.log';
        $this->assertFileExists($file);
        $this->assertStringContainsString('ENTRY-FIXTURE', file_get_contents($file));
    }

    public function test_log_directory_denies_web_access(): void
    {
        Logger::log('test|location|', 'ENTRY-FIXTURE');

        $this->assertStringContainsString('Require all denied', file_get_contents($this->directory . '/.htaccess'));
        $this->assertStringContainsString('Deny from all', file_get_contents($this->directory . '/.htaccess'));
        $this->assertFileExists($this->directory . '/index.html');
        $this->assertFileExists($this->directory . '/index.php');
    }

    public function test_log_entries_use_a_24_hour_timestamp(): void
    {
        $before = new DateTime('-1 minute');
        Logger::log('test|location|', 'ENTRY-FIXTURE');
        $after = new DateTime('+1 minute');

        $line = file_get_contents($this->directory . '/' . date('d-m-Y') . '.log');
        [$timestamp] = explode('|||', $line);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp);
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
        $this->assertGreaterThanOrEqual($before, $date);
        $this->assertLessThanOrEqual($after, $date);
    }

    public function test_write_failure_is_reported_without_an_error(): void
    {
        $blocker = trailingslashit(get_temp_dir()) . 'buckaroo-log-test-file-' . wp_generate_password(8, false);
        file_put_contents($blocker, '');
        $this->directory = $blocker . '/logs';

        $messages = [];
        // The filter runs once for every WooCommerce log handler
        $capture = function ($message, $level, $context, $handler) use (&$messages) {
            if (($context['source'] ?? '') === 'buckaroo') {
                $messages[get_class($handler)][] = $message;
            }

            return $message;
        };
        add_filter('woocommerce_logger_log_message', $capture, 10, 4);

        try {
            Logger::log('test|location|', 'ENTRY-FIXTURE');
            Logger::log('test|location|', 'ENTRY-FIXTURE');
        } finally {
            remove_filter('woocommerce_logger_log_message', $capture, 10);
            unlink($blocker);
        }

        $this->assertNotEmpty($messages);
        foreach ($messages as $handlerMessages) {
            $this->assertCount(1, $handlerMessages);
            $this->assertStringNotContainsString('ENTRY-FIXTURE', $handlerMessages[0]);
        }
    }

    // Cleanup

    public function test_cleanup_removes_only_expired_daily_log_files(): void
    {
        $retention = CronEvents::LOG_STALE_DAYS;
        $today = $this->createLogFile('today');
        $lastKept = $this->createLogFile('-' . $retention . ' days');
        $expired = $this->createLogFile('-' . ($retention + 1) . ' days');
        $longExpired = $this->createLogFile('-60 days');

        $kept = ['debug.log', 'not-a-date.log', '31-02-2020.log', '00-00-0000.log', '1-01-2020.log', 'notes.txt', '01-01-2020.log.bak'];
        foreach ($kept as $name) {
            file_put_contents($this->directory . '/' . $name, 'OTHER-FILE');
        }

        (new CronEvents())->clean_logger_storage();

        $this->assertFileExists($this->directory . '/' . $today);
        $this->assertFileExists($this->directory . '/' . $lastKept);
        $this->assertFileDoesNotExist($this->directory . '/' . $expired);
        $this->assertFileDoesNotExist($this->directory . '/' . $longExpired);

        foreach ($kept as $name) {
            $this->assertFileExists($this->directory . '/' . $name);
        }
    }

    public function test_cleanup_without_log_directory_does_nothing(): void
    {
        (new CronEvents())->clean_logger_storage();

        $this->assertDirectoryDoesNotExist($this->directory);
    }

    /**
     * @dataProvider logFileDates
     */
    public function test_log_file_date_is_only_read_from_valid_daily_log_names($fileName, $expected): void
    {
        $date = LoggerStorage::get_log_file_date($fileName);

        $this->assertSame($expected, $date === null ? null : $date->format('Y-m-d'));
    }

    public function logFileDates(): array
    {
        return [
            'valid' => ['29-02-2024.log', '2024-02-29'],
            'not a leap year' => ['29-02-2023.log', null],
            'day 32' => ['32-01-2024.log', null],
            'without extension' => ['01-01-2024', null],
            'other text' => ['debug.log', null],
            'not a string' => [null, null],
        ];
    }

    private function createLogFile(string $relativeDate, string $content = 'LOG-LINE'): string
    {
        wp_mkdir_p($this->directory);
        $fileName = date('d-m-Y', strtotime($relativeDate)) . '.log';
        file_put_contents($this->directory . '/' . $fileName, $content);

        return $fileName;
    }

    private function setRequest($fileName, $nonce): void
    {
        $_GET = ['action' => ReportDownload::ACTION, ReportDownload::FILE_PARAMETER => $fileName];

        if ($nonce !== null) {
            $_GET['_wpnonce'] = $nonce;
        }
    }

    private function assertErrorStatus(int $status, $result): void
    {
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame($status, $result->get_error_data());
    }

    private function resetWriteFailureFlag(): void
    {
        $property = new ReflectionProperty(LoggerStorage::class, 'fileWriteFailureReported');
        $property->setAccessible(true);
        $property->setValue(null, false);
    }

    private function removeDirectory(string $directory): void
    {
        $directory = untrailingslashit($directory);

        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $name) {
            if ($name !== '.' && $name !== '..') {
                is_dir($directory . '/' . $name) ? $this->removeDirectory($directory . '/' . $name) : unlink($directory . '/' . $name);
            }
        }

        rmdir($directory);
    }
}
