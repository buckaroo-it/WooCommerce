<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Admin\GeneralSettings;
use Buckaroo\Woocommerce\Admin\PaymentMethodSettings;
use Buckaroo\Woocommerce\Admin\ReportPage;
use Buckaroo\Woocommerce\Hooks\CronEvents;
use Buckaroo\Woocommerce\Services\LoggerStorage;

/**
 * The Reports section: the overview of logged days and the viewer of one day,
 * from file and database storage.
 */
class Test_ReportPage extends WP_UnitTestCase
{
    private const MASTER_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    private $originalMaster;

    private $originalGet;

    /** @var string */
    private $uploads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalMaster = get_option(self::MASTER_OPTION, null);
        $this->originalGet = $_GET;
        $this->setMaster(['debugmode' => 'on', 'logstorage' => LoggerStorage::STORAGE_FILE]);

        $this->uploads = sys_get_temp_dir() . '/bk-report-page-' . uniqid();
        mkdir($this->uploads);
        add_filter('upload_dir', [$this, 'filterUploadDir']);
        $this->resetPreparedFlag();
        $this->createLogTable();
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        remove_filter('upload_dir', [$this, 'filterUploadDir']);
        $this->resetPreparedFlag();
        $this->removeTree($this->uploads);
        if ($this->originalMaster === null) {
            delete_option(self::MASTER_OPTION);
        } else {
            update_option(self::MASTER_OPTION, $this->originalMaster);
        }
        parent::tearDown();
    }

    public function filterUploadDir(array $uploads): array
    {
        $uploads['basedir'] = $this->uploads;

        return $uploads;
    }

    public function test_empty_overview_with_debug_mode_off_offers_to_turn_it_on(): void
    {
        $this->setMaster(['debugmode' => 'off']);

        $html = $this->overview();

        $this->assertStringContainsString('No logs yet', $html);
        $this->assertStringContainsString('Turn on debug mode to log what the plugin does', $html);
        $this->assertStringContainsString('Turn on debug mode</a>', $html);
        $this->assertStringContainsString('section=advanced', $html);
        $this->assertStringContainsString('Debug mode off', $html);
        $this->assertStringContainsString('bk-status--disabled', $html);
        $this->assertStringContainsString('Kept for ' . CronEvents::LOG_STALE_DAYS . ' days', $html);
    }

    public function test_empty_overview_with_debug_mode_on_explains_when_logs_appear(): void
    {
        $html = $this->overview();

        $this->assertStringContainsString('Logs appear here as soon as the plugin handles a payment', $html);
        $this->assertStringNotContainsString('Turn on debug mode</a>', $html);
        $this->assertStringContainsString('Debug mode on', $html);
        $this->assertStringContainsString('bk-status--live', $html);
    }

    /**
     * @dataProvider storageLabelProvider
     */
    public function test_header_names_the_storage(string $storage, string $label): void
    {
        $this->setMaster(['logstorage' => $storage]);

        $this->assertStringContainsString($label, $this->overview());
    }

    public function storageLabelProvider(): array
    {
        return [
            'files' => [LoggerStorage::STORAGE_FILE, 'Stored in files<'],
            'database' => [LoggerStorage::STORAGE_DB, 'Stored in the database'],
            'both' => [LoggerStorage::STORAGE_ALL, 'Stored in files and the database'],
            'unknown' => ['somewhere', 'Stored in files<'],
        ];
    }

    public function test_overview_lists_logged_days_newest_first_with_counts(): void
    {
        $today = date('d-m-Y');
        $yesterday = date('d-m-Y', strtotime('-1 day'));
        $lastYear = '15-06-' . ((int) date('Y') - 1);
        $this->writeEntries($today, [
            ['10:00:00', 'req.1', 'Test::a', 'all good'],
            ['10:00:01', 'req.1', 'Test::b|error|', 'Something failed'],
            ['10:00:02', 'req.1', 'Test::c', 'RuntimeException: boom in /var/www/file.php:12'],
        ]);
        $this->writeEntries($yesterday, [['09:00:00', 'req.2', 'Test::a', 'fine']]);
        $this->writeEntries($lastYear, [['08:00:00', 'req.3', 'Test::a', 'fine']]);

        $html = $this->overview();

        $this->assertLessThan(strpos($html, 'log_date=' . $yesterday), strpos($html, 'log_date=' . $today));
        $this->assertLessThan(strpos($html, 'log_date=' . $lastYear), strpos($html, 'log_date=' . $yesterday));
        $this->assertStringContainsString('>Today<', $html);
        $this->assertStringContainsString('>Yesterday<', $html);
        $this->assertStringContainsString('3 entries', $html);
        $this->assertStringContainsString('1 entry', $html);
        $this->assertStringContainsString('2 errors', $html);
        $this->assertStringContainsString(date_i18n('D j F', strtotime($today . ' 12:00')), $html);
        $this->assertStringContainsString('15 June ' . ((int) date('Y') - 1), $html);
        $this->assertMatchesRegularExpression('/&middot;<\/span>\d+ B/', $html);
        $this->assertSame(3, substr_count($html, 'action=buckaroo_download_log'));
    }

    public function test_sizes_from_one_kilobyte_are_shown_with_a_decimal(): void
    {
        $this->writeEntries(date('d-m-Y'), [['10:00:00', 'r', 'Test::a', str_repeat('x', 2000)]]);

        $this->assertMatchesRegularExpression('/&middot;<\/span>2\.0 KB/', $this->overview());
    }

    public function test_an_unreadable_log_file_counts_as_empty(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can read any file');
        }
        $file = $this->writeEntries(date('d-m-Y'), [['10:00:00', 'r', 'Test::a', 'hidden']]);
        chmod($file, 0000);

        try {
            $html = $this->overview();
        } finally {
            chmod($file, 0644);
        }

        $this->assertStringContainsString('0 entries', $html);
    }

    public function test_overview_from_the_database_has_no_download_buttons(): void
    {
        $this->setMaster(['logstorage' => LoggerStorage::STORAGE_DB]);
        $this->insertRow('2026-11-02 10:00:00', 'r1', 'Test::a', 'fine');
        $this->insertRow('2026-11-02 10:00:01', 'r1', 'Test::a|error|', 'failed');
        $this->insertRow('2026-11-02 10:00:02', 'r1', 'Test::a', 'LogicException: wrong');
        $this->insertRow('2026-11-01 10:00:00', 'r2', 'Test::a', 'fine');

        $html = $this->overview();

        $this->assertLessThan(strpos($html, 'log_date=01-11-2026'), strpos($html, 'log_date=02-11-2026'));
        $this->assertStringContainsString('3 entries', $html);
        $this->assertStringContainsString('2 errors', $html);
        $this->assertStringNotContainsString('action=buckaroo_download_log', $html);
    }

    /**
     * @dataProvider missingDayProvider
     */
    public function test_viewer_reports_a_missing_day(string $storage, string $date): void
    {
        $this->setMaster(['logstorage' => $storage]);

        $html = $this->viewer($date);

        $this->assertStringContainsString('No log found for this day', $html);
        $this->assertStringContainsString('All logs', $html);
    }

    public function missingDayProvider(): array
    {
        return [
            'invalid date' => [LoggerStorage::STORAGE_FILE, '../wp-config'],
            'no file' => [LoggerStorage::STORAGE_FILE, '01-01-2020'],
            'no rows' => [LoggerStorage::STORAGE_DB, '01-01-2020'],
        ];
    }

    public function test_viewer_shows_the_entries_of_a_day_newest_first(): void
    {
        $date = '02-11-2026';
        $file = $this->writeEntries($date, [
            ['10:00:00', '69aa1b2c3d4e5f.12345678', 'Buckaroo\\Woocommerce\\Gateways\\Ideal\\IdealProcessor::handle|1|', 'first'],
            ['10:00:01', '', '', 'no location or request'],
            ['10:00:02', 'req.9', 'Test::push|PushReceived|', "array (\n  'status' => 190,\n)"],
            ['10:00:03', 'req.9', 'Test::fail', 'RuntimeException: boom in /var/www/file.php:12'],
            ['10:00:04', 'req.9', 'Test::long', str_repeat('a', 300)],
        ]);
        // A line before the first entry is ignored
        file_put_contents($file, "stray line\n" . file_get_contents($file));

        $html = $this->viewer($date);

        $this->assertStringContainsString('Monday 2 November 2026', $html);
        $this->assertStringContainsString('5 entries · 1 error', $html);
        $this->assertStringContainsString('action=buckaroo_download_log', $html);
        $this->assertLessThan(strpos($html, '10:00:00'), strpos($html, '10:00:04'));
        $this->assertStringNotContainsString('stray line', $html);
        // Namespace and numeric markers are left out of the location
        $this->assertStringContainsString('<code>Gateways\\Ideal\\IdealProcessor::handle</code>', $html);
        $this->assertStringNotContainsString('bk-logs-chip">1<', $html);
        // The location's trailing pipe is not part of the message
        $this->assertStringContainsString('<span class="bk-log-entry__summary">first</span>', $html);
        // Named tags become a chip, the request links to a filter on it
        $this->assertStringContainsString('<span class="bk-logs-chip">PushReceived</span>', $html);
        $this->assertStringContainsString('#345678</a>', $html);
        $this->assertStringContainsString('request=req.9', $html);
        // Multi-line messages show their first line and expand to the full message
        $this->assertStringContainsString('array (</span>', $html);
        $this->assertStringContainsString('&#039;status&#039; =&gt; 190,', $html);
        // An exception's file and line stay out of the summary
        $this->assertStringContainsString('<span class="bk-log-entry__summary">RuntimeException: boom</span>', $html);
        $this->assertStringContainsString('class="bk-log-entry is-error"', $html);
        $this->assertStringContainsString('>Error<', $html);
        // Long lines are cut in the summary
        $this->assertStringContainsString(str_repeat('a', 220) . '…</span>', $html);
        $this->assertStringContainsString(' data-single-line>', $html);
        $this->assertStringNotContainsString('bk-logs-pagination', $html);
    }

    public function test_errors_filter_shows_only_errors(): void
    {
        $this->writeSampleDay();
        $_GET['errors'] = '1';

        $html = $this->viewer('02-11-2026');

        $this->assertStringContainsString('payment failed', $html);
        $this->assertStringNotContainsString('payment started', $html);
        $this->assertMatchesRegularExpression('/bk-logs-filter is-active"[^>]*errors=1/', $html);
    }

    public function test_request_filter_shows_one_request_and_can_be_removed(): void
    {
        $this->writeSampleDay();
        $_GET['request'] = 'req.2<script>';

        $html = $this->viewer('02-11-2026');

        $this->assertStringContainsString('push received', $html);
        $this->assertStringNotContainsString('payment started', $html);
        $this->assertStringContainsString('Request req2', $html);
        $this->assertStringContainsString('bk-logs-filter--removable', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_search_matches_messages_locations_and_tags_without_case(): void
    {
        $this->writeSampleDay();

        $_GET['s'] = 'PUSH RECEIVED';
        $this->assertStringContainsString('push received', $this->viewer('02-11-2026'));

        $_GET['s'] = 'idealprocessor';
        $html = $this->viewer('02-11-2026');
        $this->assertStringContainsString('payment started', $html);
        $this->assertStringNotContainsString('push received', $html);
        $this->assertStringContainsString('value="idealprocessor"', $html);

        $_GET['s'] = 'paymentrequest';
        $this->assertStringContainsString('payment started', $this->viewer('02-11-2026'));
    }

    public function test_viewer_says_when_no_entry_matches(): void
    {
        $this->writeSampleDay();
        $_GET['s'] = 'nothing like this';

        $html = $this->viewer('02-11-2026');

        $this->assertStringContainsString('No entries match these filters', $html);
        $this->assertStringContainsString('Show all entries', $html);
    }

    public function test_long_days_are_paginated(): void
    {
        $entries = [];
        for ($i = 0; $i < 120; $i++) {
            $entries[] = [sprintf('10:%02d:%02d', intdiv($i, 60), $i % 60), 'r', 'Test::a', 'entry ' . $i];
        }
        $this->writeEntries('02-11-2026', $entries);

        $html = $this->viewer('02-11-2026');
        $this->assertStringContainsString('bk-logs-pagination', $html);
        $this->assertSame(ReportPage::PER_PAGE, substr_count($html, '<details'));
        $this->assertStringContainsString('entry 119<', $html);

        $_GET['paged'] = '3';
        $html = $this->viewer('02-11-2026');
        $this->assertSame(20, substr_count($html, '<details'));
        $this->assertStringContainsString('entry 0<', $html);
        $this->assertStringContainsString('paged=2', $html);
    }

    public function test_viewer_reads_the_database_when_that_is_the_only_storage(): void
    {
        $this->setMaster(['logstorage' => LoggerStorage::STORAGE_DB]);
        $this->insertRow('2026-11-02 10:00:00', 'r1', 'Test::a', 'on the day');
        $this->insertRow('2026-11-02 23:59:59', 'r1', 'Test::a', 'end of the day');
        $this->insertRow('2026-11-03 00:00:00', 'r2', 'Test::a', 'next day');

        $html = $this->viewer('02-11-2026');

        $this->assertStringContainsString('2 entries', $html);
        $this->assertLessThan(strpos($html, 'on the day'), strpos($html, 'end of the day'));
        $this->assertStringNotContainsString('next day', $html);
        $this->assertStringNotContainsString('action=buckaroo_download_log', $html);
    }

    public function test_report_url_leaves_out_empty_arguments(): void
    {
        $url = ReportPage::report_url(['log_date' => '02-11-2026', 's' => '', 'request' => null, 'errors' => false]);

        $this->assertSame(admin_url('admin.php?page=wc-settings&tab=buckaroo_settings&section=report&log_date=02-11-2026'), $url);
    }

    public function test_helpers_cope_with_unexpected_input(): void
    {
        $page = new class () extends ReportPage {
            public function call(string $method, ...$args)
            {
                return $this->{$method}(...$args);
            }
        };

        $this->assertSame('not-a-date', $page->call('format_day', 'not-a-date'));
        $this->assertSame(['', false], $page->call('summarize', "  \n "));
        $this->assertSame('', $page->call('relative_day', '01-01-2020'));
    }

    public function test_settings_page_routes_the_reports_section(): void
    {
        global $current_section;
        $previous = $current_section;
        $current_section = 'report';
        $this->writeSampleDay();
        $settings = new GeneralSettings(new PaymentMethodSettings());

        try {
            ob_start();
            $settings->output();
            $overview = ob_get_clean();

            $_GET['log_date'] = '02-11-2026';
            ob_start();
            $settings->output();
            $viewer = ob_get_clean();
        } finally {
            $current_section = $previous;
        }

        $this->assertStringContainsString('bk-logs-days', $overview);
        $this->assertStringContainsString('payment started', $viewer);
    }

    private function overview(): string
    {
        ob_start();
        (new ReportPage())->output_report();

        return (string) ob_get_clean();
    }

    private function viewer(string $date): string
    {
        $this->resetPreparedFlag();
        ob_start();
        (new ReportPage())->display_log_file($date);

        return (string) ob_get_clean();
    }

    private function writeSampleDay(): void
    {
        $this->writeEntries('02-11-2026', [
            ['10:00:00', 'req.1', 'Buckaroo\\Woocommerce\\Gateways\\Ideal\\IdealProcessor::handle|PaymentRequest|', 'payment started'],
            ['10:00:01', 'req.1', 'Test::process|error|', 'payment failed'],
            ['10:00:02', 'req.2', 'Test::push', 'push received'],
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $entries  time, request, location, message
     */
    private function writeEntries(string $date, array $entries): string
    {
        LoggerStorage::prepare_file_storage();
        $day = DateTime::createFromFormat('!d-m-Y', $date)->format('Y-m-d');
        $lines = '';
        foreach ($entries as [$time, $request, $location, $message]) {
            $lines .= implode('|||', [$day . ' ' . $time, $request, $location, $message]) . PHP_EOL;
        }
        $file = LoggerStorage::get_file_storage_location() . LoggerStorage::get_log_file_name($date);
        file_put_contents($file, $lines);

        return $file;
    }

    private function insertRow(string $date, string $request, string $location, string $message): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE, [
            'date' => $date,
            'process_id' => $request,
            'location_id' => $location,
            'message' => $message,
        ]);
    }

    private function createLogTable(): void
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
        foreach (scandir($path) as $name) {
            if ($name !== '.' && $name !== '..') {
                $this->removeTree($path . '/' . $name);
            }
        }
        rmdir($path);
    }
}
