<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Admin\ReportPage;
use Buckaroo\Woocommerce\Hooks\CronEvents;
use Buckaroo\Woocommerce\Install\Migration\Versions\SetupTransactionsAndLogs;
use Buckaroo\Woocommerce\Services\LoggerStorage;

/**
 * Log entries kept in the database: Report page listing and cleanup.
 */
class Test_LogDatabaseStorage extends WP_UnitTestCase
{
    private const MASTER_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    private $originalMaster;

    private $originalGet;

    private $originalRequest;

    /** @var string */
    private $table;

    protected function setUp(): void
    {
        global $wpdb;

        parent::setUp();
        $this->originalMaster = get_option(self::MASTER_OPTION, null);
        $this->originalGet = $_GET;
        $this->originalRequest = $_REQUEST;
        $_GET = [];
        $_REQUEST = [];

        update_option(self::MASTER_OPTION, [
            'debugmode' => 'on',
            'logstorage' => LoggerStorage::STORAGE_DB,
        ]);

        $this->table = $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE;

        // Same schema as an installed shop.
        $createLogTable = new ReflectionMethod(SetupTransactionsAndLogs::class, 'create_log_table');
        $createLogTable->setAccessible(true);
        $createLogTable->invoke(new SetupTransactionsAndLogs());

        $wpdb->query("DELETE FROM {$this->table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_REQUEST = $this->originalRequest;

        if ($this->originalMaster === null) {
            delete_option(self::MASTER_OPTION);
        } else {
            update_option(self::MASTER_OPTION, $this->originalMaster);
        }

        parent::tearDown();
    }

    // Report page

    public function test_total_count_is_zero_for_an_empty_table(): void
    {
        $this->assertSame(0, (new ReportPage())->get_total_count_database());
    }

    public function test_total_count_matches_the_stored_entries(): void
    {
        $this->insertEntries(3);

        $this->assertSame(3, (new ReportPage())->get_total_count_database());
    }

    public function test_entries_are_listed_newest_first_in_pages_of_twenty(): void
    {
        $this->insertEntries(45);

        $this->assertSame($this->expectedMessages(45, 1, 20), $this->messagesOnPage(1));
        $this->assertSame($this->expectedMessages(45, 21, 40), $this->messagesOnPage(2));
        $this->assertSame($this->expectedMessages(45, 41, 45), $this->messagesOnPage(3));
        $this->assertSame([], $this->messagesOnPage(4));
    }

    public function test_listed_entries_carry_the_stored_date_and_an_escaped_message(): void
    {
        $this->insertEntry('2026-01-02 03:04:05', '<b>message</b>');

        $items = $this->pageItems(1);

        $this->assertCount(1, $items);
        $this->assertSame('2026-01-02 03:04:05', $items[0]['date']);
        $this->assertSame(
            "<code style='display:block;padding:10px;'><pre>&lt;b&gt;message&lt;/b&gt;</pre></code>",
            $items[0]['description']
        );
    }

    public function test_report_list_paginates_over_the_stored_entries(): void
    {
        $this->insertEntries(45);
        // WP_List_Table::get_pagenum() reads the page from $_REQUEST.
        $_GET['paged'] = '2';
        $_REQUEST['paged'] = '2';

        $page = new ReportPage();
        $page->prepare_items();

        $this->assertSame(45, $page->get_pagination_arg('total_items'));
        $this->assertEquals(3, $page->get_pagination_arg('total_pages'));
        $this->assertSame(
            $this->expectedMessages(45, 21, 40),
            array_map(function ($item) {
                return $this->plainMessage($item['description']);
            }, $page->items)
        );
    }

    public function test_report_list_is_empty_without_entries(): void
    {
        $page = new ReportPage();
        $page->prepare_items();

        $this->assertSame(0, $page->get_pagination_arg('total_items'));
        $this->assertSame([], $page->items);
    }

    // Cleanup

    public function test_cleanup_removes_only_entries_older_than_the_retention_period(): void
    {
        $staleDate = (new DateTime())->sub(new DateInterval('P' . CronEvents::LOG_STALE_DAYS . 'D'));

        $this->insertEntry((new DateTime())->format('Y-m-d H:i:s'), 'now');
        $this->insertEntry((clone $staleDate)->modify('+1 minute')->format('Y-m-d H:i:s'), 'just-inside');
        $this->insertEntry((clone $staleDate)->modify('-1 minute')->format('Y-m-d H:i:s'), 'just-outside');
        $this->insertEntry((new DateTime())->modify('-60 days')->format('Y-m-d H:i:s'), 'long-expired');

        (new CronEvents())->clean_logger_storage();

        $this->assertSame(['just-inside', 'now'], $this->storedMessages());
    }

    public function test_cleanup_of_an_empty_table_does_nothing(): void
    {
        global $wpdb;

        (new CronEvents())->clean_logger_storage();

        $this->assertSame('', $wpdb->last_error);
        $this->assertSame([], $this->storedMessages());
    }

    public function test_cleanup_with_file_storage_leaves_database_entries_alone(): void
    {
        update_option(self::MASTER_OPTION, [
            'debugmode' => 'on',
            'logstorage' => LoggerStorage::STORAGE_FILE,
        ]);
        add_filter('buckaroo_log_directory', [$this, 'missingDirectory']);

        $this->insertEntry((new DateTime())->modify('-60 days')->format('Y-m-d H:i:s'), 'long-expired');

        try {
            (new CronEvents())->clean_logger_storage();
        } finally {
            remove_filter('buckaroo_log_directory', [$this, 'missingDirectory']);
        }

        $this->assertSame(['long-expired'], $this->storedMessages());
    }

    public function missingDirectory(): string
    {
        return trailingslashit(get_temp_dir()) . 'buckaroo-log-test-missing-' . wp_generate_password(8, false);
    }

    /** Entry n is n minutes older than entry 1, so entry 1 is the newest. */
    private function insertEntries(int $count): void
    {
        $base = new DateTime('2026-06-01 12:00:00');

        for ($n = 1; $n <= $count; $n++) {
            $this->insertEntry((clone $base)->modify('-' . $n . ' minutes')->format('Y-m-d H:i:s'), 'entry-' . $n);
        }
    }

    private function insertEntry(string $date, string $message): void
    {
        global $wpdb;

        $wpdb->insert($this->table, [
            'date' => $date,
            'process_id' => 'test',
            'message' => $message,
            'location_id' => 'test',
        ]);
    }

    private function expectedMessages(int $count, int $from, int $to): array
    {
        $messages = [];

        for ($n = $from; $n <= min($to, $count); $n++) {
            $messages[] = 'entry-' . $n;
        }

        return $messages;
    }

    private function pageItems(int $page): array
    {
        $method = new ReflectionMethod(ReportPage::class, 'get_page_item_from_database');
        $method->setAccessible(true);

        return $method->invoke(new ReportPage(), $page);
    }

    private function messagesOnPage(int $page): array
    {
        return array_map(function ($item) {
            return $this->plainMessage($item['description']);
        }, $this->pageItems($page));
    }

    private function plainMessage(string $description): string
    {
        return html_entity_decode(wp_strip_all_tags($description));
    }

    private function storedMessages(): array
    {
        global $wpdb;

        return $wpdb->get_col("SELECT `message` FROM {$this->table} ORDER BY `message` ASC"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}
