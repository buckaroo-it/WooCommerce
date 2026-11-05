<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Hooks\CronEvents;
use Buckaroo\Woocommerce\Hooks\ReportDownload;
use Buckaroo\Woocommerce\Services\LoggerStorage;

/**
 * Log files live in a protected folder in uploads, can be downloaded by shop
 * managers only and are cleaned up after LOG_STALE_DAYS.
 */
class Test_LogFileStorage extends WP_UnitTestCase
{
    private const MASTER_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    private $originalMaster;

    /** @var string uploads base folder for this test */
    private $uploads;

    /** @var string|null basedir the upload_dir filter returns */
    private $basedir;

    /** @var string[] legacy files this test created */
    private $legacyFiles = [];

    /** @var bool */
    private $createdLegacyDirectory = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalMaster = get_option(self::MASTER_OPTION, null);
        $this->setMaster(['debugmode' => 'on', 'logstorage' => LoggerStorage::STORAGE_FILE]);

        $this->uploads = sys_get_temp_dir() . '/bk-log-storage-' . uniqid();
        mkdir($this->uploads);
        $this->basedir = $this->uploads;
        add_filter('upload_dir', [$this, 'filterUploadDir']);
        $this->resetPreparedFlag();
    }

    protected function tearDown(): void
    {
        remove_filter('upload_dir', [$this, 'filterUploadDir']);
        $this->resetPreparedFlag();
        foreach ($this->legacyFiles as $file) {
            @unlink($file);
        }
        if ($this->createdLegacyDirectory) {
            @rmdir($this->legacyDirectory());
        }
        $this->removeTree($this->uploads);
        if ($this->originalMaster === null) {
            delete_option(self::MASTER_OPTION);
        } else {
            update_option(self::MASTER_OPTION, $this->originalMaster);
        }
        unset($_GET['date'], $_REQUEST['_wpnonce']);
        CronEvents::unschedule();
        parent::tearDown();
    }

    public function filterUploadDir(array $uploads): array
    {
        $uploads['basedir'] = $this->basedir;

        return $uploads;
    }

    public function test_logs_are_stored_in_uploads(): void
    {
        $this->assertSame($this->uploads . '/buckaroo-logs/', LoggerStorage::get_file_storage_location());
    }

    public function test_falls_back_to_wp_content_when_the_uploads_folder_is_unknown(): void
    {
        $this->basedir = '';

        $this->assertSame(WP_CONTENT_DIR . '/uploads/buckaroo-logs/', LoggerStorage::get_file_storage_location());
    }

    public function test_file_name_holds_the_date_and_an_unguessable_hash(): void
    {
        $name = LoggerStorage::get_log_file_name('05-11-2026');

        $this->assertMatchesRegularExpression('/^05-11-2026-[a-f0-9]{32}\.log$/', $name);
        $this->assertSame($name, LoggerStorage::get_log_file_name('05-11-2026'));
        $this->assertNotSame(substr($name, 11), substr(LoggerStorage::get_log_file_name('06-11-2026'), 11));
    }

    /**
     * @dataProvider logDateProvider
     */
    public function test_only_real_d_m_y_dates_are_log_dates(string $date, bool $expected): void
    {
        $this->assertSame($expected, LoggerStorage::is_log_date($date));
    }

    public function logDateProvider(): array
    {
        return [
            'valid' => ['05-11-2026', true],
            'leap day' => ['29-02-2028', true],
            'no such day' => ['31-02-2026', false],
            'other format' => ['2026-11-05', false],
            'no leading zero' => ['5-11-2026', false],
            'path' => ['../05-11-2026', false],
            'empty' => ['', false],
        ];
    }

    public function test_preparing_creates_a_folder_closed_to_the_web(): void
    {
        $this->assertTrue(LoggerStorage::prepare_file_storage());

        $directory = LoggerStorage::get_file_storage_location();
        $this->assertStringContainsString('Require all denied', file_get_contents($directory . '.htaccess'));
        $this->assertStringContainsString('Deny from all', file_get_contents($directory . '.htaccess'));
        $this->assertFileExists($directory . 'index.html');
        $this->assertStringContainsString('Silence is golden', file_get_contents($directory . 'index.php'));
    }

    public function test_preparing_keeps_existing_protection_files_and_runs_once_per_request(): void
    {
        $directory = LoggerStorage::get_file_storage_location();
        mkdir($directory);
        file_put_contents($directory . '.htaccess', 'custom rules');

        $this->assertTrue(LoggerStorage::prepare_file_storage());
        $this->assertSame('custom rules', file_get_contents($directory . '.htaccess'));

        // Later calls in the same request only check the folder still exists
        $this->removeTree($directory);
        $this->assertFalse(LoggerStorage::prepare_file_storage());
    }

    public function test_nothing_is_written_when_the_folder_cannot_be_created(): void
    {
        $blocker = $this->uploads . '/not-a-folder';
        file_put_contents($blocker, '');
        $this->basedir = $blocker;

        $this->assertFalse(LoggerStorage::prepare_file_storage());

        LoggerStorage::get_instance()->log('Test::method', 'not written');
        $this->assertSame([], LoggerStorage::get_log_files());
    }

    public function test_entries_are_appended_to_the_file_of_the_day_with_a_24_hour_clock(): void
    {
        LoggerStorage::get_instance()->log('Test::method|tag|', 'first');
        LoggerStorage::get_instance()->log('Test::method', 'second');

        $file = LoggerStorage::get_log_file_path(date('d-m-Y'));
        $this->assertNotNull($file);
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $this->assertCount(2, $lines);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} ([01]\d|2[0-3]):\d{2}:\d{2}\|\|\|[^|]+\|\|\|Test::method\|tag\|\|\|\|first$/', $lines[0]);
        $this->assertStringEndsWith('|||Test::method|||second', $lines[1]);
    }

    public function test_log_files_are_listed_newest_first_and_foreign_files_are_ignored(): void
    {
        $this->writeLog('01-11-2026', 'a');
        $this->writeLog('03-11-2026', 'b');
        $this->writeLog('15-10-2026', 'c');
        $directory = LoggerStorage::get_file_storage_location();
        file_put_contents($directory . '02-11-2026.log', 'no hash');
        file_put_contents($directory . '04-11-2026-0123456789abcdef0123456789abcdef.log', 'wrong hash');
        file_put_contents($directory . 'notes.log', 'not a log');

        $files = LoggerStorage::get_log_files();

        $this->assertSame(['03-11-2026', '01-11-2026', '15-10-2026'], array_keys($files));
        $this->assertSame($directory . LoggerStorage::get_log_file_name('03-11-2026'), $files['03-11-2026']);
    }

    public function test_log_file_path_is_null_for_invalid_or_missing_days(): void
    {
        $file = $this->writeLog('01-11-2026', 'a');

        $this->assertSame($file, LoggerStorage::get_log_file_path('01-11-2026'));
        $this->assertNull(LoggerStorage::get_log_file_path('02-11-2026'));
        $this->assertNull(LoggerStorage::get_log_file_path('../../wp-config'));
    }

    public function test_logs_in_the_old_public_folder_are_moved_to_the_protected_folder(): void
    {
        $this->requireEmptyLegacyDirectory();
        $this->writeLegacy('01-10-2026.log', "old entry\n");

        LoggerStorage::prepare_file_storage();

        $this->assertFileDoesNotExist($this->legacyDirectory() . '01-10-2026.log');
        $this->assertSame("old entry\n", file_get_contents(LoggerStorage::get_log_file_path('01-10-2026')));
    }

    public function test_old_entries_are_put_in_front_when_the_day_already_has_a_new_log(): void
    {
        $this->requireEmptyLegacyDirectory();
        $this->writeLog('02-10-2026', "new entry\n");
        $this->resetPreparedFlag();
        $this->writeLegacy('02-10-2026.log', "old entry\n");

        LoggerStorage::prepare_file_storage();

        $this->assertFileDoesNotExist($this->legacyDirectory() . '02-10-2026.log');
        $this->assertSame("old entry\nnew entry\n", file_get_contents(LoggerStorage::get_log_file_path('02-10-2026')));
    }

    public function test_old_files_that_are_not_daily_logs_are_removed(): void
    {
        $this->requireEmptyLegacyDirectory();
        $this->writeLegacy('report_log.log', 'unknown');

        LoggerStorage::prepare_file_storage();

        $this->assertFileDoesNotExist($this->legacyDirectory() . 'report_log.log');
        $this->assertSame([], LoggerStorage::get_log_files());
    }

    public function test_an_old_log_that_cannot_be_moved_is_still_removed_from_the_public_folder(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can write to any folder');
        }
        $this->requireEmptyLegacyDirectory();
        $directory = LoggerStorage::get_file_storage_location();
        mkdir($directory);
        chmod($directory, 0555);
        $this->writeLegacy('03-10-2026.log', 'old entry');

        try {
            LoggerStorage::prepare_file_storage();
        } finally {
            chmod($directory, 0755);
        }

        $this->assertFileDoesNotExist($this->legacyDirectory() . '03-10-2026.log');
        $this->assertNull(LoggerStorage::get_log_file_path('03-10-2026'));
    }

    public function test_download_of_a_missing_or_invalid_day_is_a_404(): void
    {
        foreach (['01-01-2020', '../../wp-config.php'] as $date) {
            try {
                LoggerStorage::downloadFile($date);
                $this->fail('Expected wp_die for ' . $date);
            } catch (WPDieException $e) {
                $this->assertStringContainsString('No log file found', $e->getMessage());
            }
        }
    }

    public function test_download_sends_the_file_contents(): void
    {
        $file = $this->writeLog('01-11-2026', "entry one\nentry two\n");
        $send = new ReflectionMethod(LoggerStorage::class, 'send_log_file');
        if (PHP_VERSION_ID < 80100) {
            $send->setAccessible(true);
        }

        $this->expectOutputString("entry one\nentry two\n");
        $send->invoke(null, $file, '01-11-2026');
    }

    public function test_download_is_sent_as_a_plain_text_attachment(): void
    {
        $headers = new ReflectionMethod(LoggerStorage::class, 'download_headers');
        if (PHP_VERSION_ID < 80100) {
            $headers->setAccessible(true);
        }

        $this->assertSame(
            [
                'Content-Type: text/plain; charset=utf-8',
                'Content-Disposition: attachment; filename="buckaroo-01-11-2026.log"',
                'X-Content-Type-Options: nosniff',
            ],
            $headers->invoke(null, '01-11-2026')
        );
    }

    public function test_nothing_is_logged_while_debug_mode_is_off(): void
    {
        $this->setMaster(['debugmode' => 'off']);

        LoggerStorage::get_instance()->log('Test::method', 'not written');

        $this->assertSame([], LoggerStorage::get_log_files());
    }

    public function test_an_unknown_storage_setting_logs_to_files(): void
    {
        $this->setMaster(['logstorage' => 'somewhere']);

        LoggerStorage::get_instance()->log('Test::method', 'written');

        $this->assertStringContainsString('written', file_get_contents(LoggerStorage::get_log_file_path(date('d-m-Y'))));
    }

    public function test_storage_all_logs_to_files_and_the_database(): void
    {
        global $wpdb;
        $table = $this->createLogTable();
        $this->setMaster(['logstorage' => LoggerStorage::STORAGE_ALL]);

        LoggerStorage::get_instance()->log('Test::method', 'in both');

        $this->assertStringContainsString('in both', file_get_contents(LoggerStorage::get_log_file_path(date('d-m-Y'))));
        $this->assertSame(['in both'], $wpdb->get_col("SELECT message FROM $table"));
    }

    public function test_download_handler_is_registered_for_logged_in_users_only(): void
    {
        $download = new ReportDownload();

        $this->assertSame(10, has_action('admin_post_' . ReportDownload::ACTION, [$download, 'handle']));
        $this->assertFalse(has_action('admin_post_nopriv_' . ReportDownload::ACTION));
    }

    public function test_download_url_points_to_admin_post_with_a_nonce(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        // wp_nonce_url() escapes the separators for use in HTML
        $url = html_entity_decode(ReportDownload::url('01-11-2026'));
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith(admin_url('admin-post.php'), $url);
        $this->assertSame(ReportDownload::ACTION, $query['action']);
        $this->assertSame('01-11-2026', $query['date']);
        $this->assertSame(1, wp_verify_nonce($query['_wpnonce'], ReportDownload::ACTION));
    }

    public function test_users_without_the_capability_cannot_download(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        $this->expectException(WPDieException::class);
        $this->expectExceptionMessage('You are not allowed to download Buckaroo logs.');
        (new ReportDownload())->handle();
    }

    public function test_download_without_a_valid_nonce_is_refused(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $_REQUEST['_wpnonce'] = 'not-a-nonce';
        $_GET['date'] = '01-11-2026';

        try {
            (new ReportDownload())->handle();
            $this->fail('Expected wp_die');
        } catch (WPDieException $e) {
            $this->assertStringNotContainsString('No log file found', $e->getMessage());
        }
    }

    public function test_a_valid_download_request_reaches_the_file_lookup(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $_REQUEST['_wpnonce'] = wp_create_nonce(ReportDownload::ACTION);

        foreach (['01-01-2020', null] as $date) {
            if ($date === null) {
                unset($_GET['date']);
            } else {
                $_GET['date'] = $date;
            }

            try {
                (new ReportDownload())->handle();
                $this->fail('Expected wp_die');
            } catch (WPDieException $e) {
                $this->assertStringContainsString('No log file found', $e->getMessage());
            }
        }
    }

    public function test_cleanup_is_scheduled_daily_and_can_be_unscheduled(): void
    {
        CronEvents::unschedule();
        $events = new CronEvents();

        $this->assertSame(10, has_action('buckaroo_clean_logger_storage', [$events, 'clean_logger_storage']));
        $this->assertSame('daily', wp_get_schedule('buckaroo_clean_logger_storage'));

        CronEvents::unschedule();
        $this->assertFalse(wp_next_scheduled('buckaroo_clean_logger_storage'));
    }

    public function test_cleanup_removes_log_files_older_than_the_retention_period(): void
    {
        $today = date('d-m-Y');
        $recent = date('d-m-Y', strtotime('-' . (CronEvents::LOG_STALE_DAYS - 1) . ' days'));
        $stale = date('d-m-Y', strtotime('-' . (CronEvents::LOG_STALE_DAYS + 1) . ' days'));
        foreach ([$today, $recent, $stale] as $date) {
            $this->writeLog($date, 'entry');
        }

        (new CronEvents())->clean_logger_storage();

        $this->assertSame([$today, $recent], array_keys(LoggerStorage::get_log_files()));
    }

    /**
     * @dataProvider storageProvider
     */
    public function test_cleanup_covers_the_selected_storage(string $storage, bool $cleansFiles, bool $cleansDatabase): void
    {
        global $wpdb;
        $table = $this->createLogTable();
        $old = date('Y-m-d H:i:s', strtotime('-' . (CronEvents::LOG_STALE_DAYS + 1) . ' days'));
        $wpdb->insert($table, ['date' => $old, 'process_id' => 'p', 'message' => 'old', 'location_id' => 'x']);
        $wpdb->insert($table, ['date' => current_time('mysql'), 'process_id' => 'p', 'message' => 'new', 'location_id' => 'x']);
        $this->writeLog(date('d-m-Y', strtotime('-' . (CronEvents::LOG_STALE_DAYS + 1) . ' days')), 'old');
        $this->setMaster(['logstorage' => $storage]);

        (new CronEvents())->clean_logger_storage();

        $this->assertSame($cleansFiles ? 0 : 1, count(LoggerStorage::get_log_files()));
        $this->assertSame($cleansDatabase ? ['new'] : ['old', 'new'], $wpdb->get_col("SELECT message FROM $table ORDER BY id"));
    }

    public function storageProvider(): array
    {
        return [
            'files' => [LoggerStorage::STORAGE_FILE, true, false],
            'database' => [LoggerStorage::STORAGE_DB, false, true],
            'both' => [LoggerStorage::STORAGE_ALL, true, true],
            'not set' => ['', true, true],
        ];
    }

    private function writeLog(string $date, string $contents): string
    {
        LoggerStorage::prepare_file_storage();
        $file = LoggerStorage::get_file_storage_location() . LoggerStorage::get_log_file_name($date);
        file_put_contents($file, $contents);

        return $file;
    }

    private function writeLegacy(string $name, string $contents): void
    {
        $file = $this->legacyDirectory() . $name;
        file_put_contents($file, $contents);
        $this->legacyFiles[] = $file;
    }

    private function legacyDirectory(): string
    {
        return plugin_dir_path(BK_PLUGIN_FILE) . LoggerStorage::LEGACY_FILE_LOCATION;
    }

    /** The move takes every file in the old folder, so only run on an empty one */
    private function requireEmptyLegacyDirectory(): void
    {
        $directory = $this->legacyDirectory();
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
            $this->createdLegacyDirectory = true;
        }
        if (glob($directory . '*') !== []) {
            $this->markTestSkipped('The old log folder ' . $directory . ' is not empty.');
        }
    }

    private function createLogTable(): string
    {
        global $wpdb;
        $table = $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE;
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS $table (
                `id` BIGINT NOT NULL AUTO_INCREMENT,
                `date` DATETIME NOT NULL,
                `process_id` VARCHAR(23) NOT NULL,
                `message` TEXT NOT NULL,
                `location_id` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`)
            )"
        );
        $wpdb->query("DELETE FROM $table");

        return $table;
    }

    private function setMaster(array $values): void
    {
        $master = get_option(self::MASTER_OPTION, []);
        update_option(self::MASTER_OPTION, array_replace(is_array($master) ? $master : [], $values));
    }

    private function resetPreparedFlag(): void
    {
        $flag = new ReflectionProperty(LoggerStorage::class, 'fileStoragePrepared');
        if (PHP_VERSION_ID < 80100) {
            $flag->setAccessible(true);
        }
        $flag->setValue(null, false);
    }

    private function removeTree(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }
        if (is_file($path)) {
            unlink($path);

            return;
        }
        chmod($path, 0755);
        foreach (scandir($path) as $name) {
            if ($name !== '.' && $name !== '..') {
                $this->removeTree($path . '/' . $name);
            }
        }
        rmdir($path);
    }
}
