<?php

namespace Buckaroo\Woocommerce\Admin;

use Buckaroo\Woocommerce\Hooks\CronEvents;
use Buckaroo\Woocommerce\Hooks\ReportDownload;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\LoggerStorage;
use DateTime;

/**
 * The Reports section of the Buckaroo settings: an overview of the logged days
 * and a viewer for the entries of one day.
 *
 * Logs come from the file storage, or from the database when that is the only
 * storage selected.
 */
class ReportPage
{
    public const PER_PAGE = 50;

    /**
     * First line of an entry in a log file: date|||request|||location|||message.
     * Locations end with a pipe ("Class::method|tag|"), so the separator after
     * them is the first "|||" that is not followed by another pipe.
     */
    private const FILE_ENTRY_PATTERN = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|\|\|([^|]*)\|\|\|(.*?)\|\|\|(?!\|)(.*)$/s';

    /** A logged exception starts with its class name, such as "RuntimeException: ..." */
    private const ERROR_MESSAGE_PATTERN = '/^[^\s:]*(Exception|Error): /';

    /**
     * Overview: logging status and the logged days
     */
    public function output_report()
    {
        $days = $this->get_days();
        $debugOn = Helper::get('debugmode') === 'on';
        ?>
<div class="bk-logs">
        <?php $this->render_header($debugOn); ?>

    <div class="bk-logs-card">
        <?php if ($days === []) : ?>
        <div class="bk-logs-empty">
            <span class="dashicons dashicons-media-text" aria-hidden="true"></span>
            <p class="bk-logs-empty__title"><?php esc_html_e('No logs yet', 'wc-buckaroo-bpe-gateway'); ?></p>
            <p>
                <?php
                echo esc_html(
                    $debugOn
                        ? __('Logs appear here as soon as the plugin handles a payment, refund or push.', 'wc-buckaroo-bpe-gateway')
                        : __('Turn on debug mode to log what the plugin does, for example while you investigate a problem with Buckaroo support.', 'wc-buckaroo-bpe-gateway')
                );
                ?>
            </p>
            <?php if (! $debugOn) : ?>
            <a class="button button-primary" href="<?php echo esc_url($this->settings_url()); ?>"><?php esc_html_e('Turn on debug mode', 'wc-buckaroo-bpe-gateway'); ?></a>
            <?php endif; ?>
        </div>
        <?php else : ?>
        <ul class="bk-logs-days">
            <?php foreach ($days as $day) : ?>
            <li class="bk-logs-day">
                <a class="bk-logs-day__main" href="<?php echo esc_url(self::report_url(['log_date' => $day['date']])); ?>">
                    <span class="bk-logs-day__date">
                        <?php echo esc_html($this->format_day($day['date'])); ?>
                        <?php if ($this->relative_day($day['date']) !== '') : ?>
                        <span class="bk-logs-chip"><?php echo esc_html($this->relative_day($day['date'])); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="bk-logs-day__meta">
                        <?php echo esc_html($this->count_label($day['entries'], 0)); ?>
                        <?php if ($day['size'] !== null) : ?>
                        <span class="bk-meta-divider">&middot;</span><?php echo esc_html(size_format($day['size'], $day['size'] < KB_IN_BYTES ? 0 : 1)); ?>
                        <?php endif; ?>
                        <?php if ($day['errors'] > 0) : ?>
                        <span class="bk-logs-day__errors"><span class="bk-meta-divider">&middot;</span>
                            <?php
                            /* translators: %d: number of errors */
                            echo esc_html(sprintf(_n('%d error', '%d errors', $day['errors'], 'wc-buckaroo-bpe-gateway'), $day['errors']));
                            ?>
                        </span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php if ($day['errors'] > 0) : ?>
                <span class="bk-logs-badge bk-logs-badge--error bk-logs-day__badge">
                    <?php
                    /* translators: %d: number of errors */
                    echo esc_html(sprintf(_n('%d error', '%d errors', $day['errors'], 'wc-buckaroo-bpe-gateway'), $day['errors']));
                    ?>
                </span>
                <?php endif; ?>
                <span class="bk-logs-day__actions">
                    <a class="button button-primary" href="<?php echo esc_url(self::report_url(['log_date' => $day['date']])); ?>"><?php esc_html_e('View', 'wc-buckaroo-bpe-gateway'); ?></a>
                    <?php if ($day['size'] !== null) : ?>
                    <a class="button button-primary bk-logs-icon-button" href="<?php echo esc_url(ReportDownload::url($day['date'])); ?>" title="<?php esc_attr_e('Download', 'wc-buckaroo-bpe-gateway'); ?>">
                        <span class="dashicons dashicons-download" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Download', 'wc-buckaroo-bpe-gateway'); ?></span>
                    </a>
                    <?php endif; ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
        <?php
    }

    /**
     * Viewer: the entries of one day, newest first
     *
     * @param  string  $date  d-m-Y
     */
    public function display_log_file($date)
    {
        $date = (string) $date;
        $filters = $this->get_filters();
        $result = LoggerStorage::is_log_date($date) ? $this->get_entries($date, $filters) : null;
        $fromFile = $this->uses_file_storage();
        ?>
<div class="bk-logs">
    <a class="bk-logs-back" href="<?php echo esc_url(self::report_url()); ?>">
        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><?php esc_html_e('All logs', 'wc-buckaroo-bpe-gateway'); ?>
    </a>

        <?php if ($result === null) : ?>
    <div class="bk-logs-card">
        <div class="bk-logs-empty">
            <p class="bk-logs-empty__title"><?php esc_html_e('No log found for this day', 'wc-buckaroo-bpe-gateway'); ?></p>
            <p><?php esc_html_e('Logs are kept for a limited time and may have been removed.', 'wc-buckaroo-bpe-gateway'); ?></p>
        </div>
    </div>
</div>
            <?php
            return;
        endif;
        ?>

    <div class="bk-logs-card bk-logs-header">
        <div class="bk-logs-header__text">
            <h2><?php echo esc_html($this->format_day($date, true)); ?></h2>
            <p class="description"><?php echo esc_html($this->count_label($result['day_entries'], $result['day_errors'])); ?></p>
        </div>
        <?php if ($fromFile) : ?>
        <div class="bk-logs-header__actions">
            <a class="button button-primary" href="<?php echo esc_url(ReportDownload::url($date)); ?>">
                <span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e('Download', 'wc-buckaroo-bpe-gateway'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

        <?php $this->render_toolbar($date, $filters, $result['day_errors']); ?>

    <div class="bk-logs-card bk-logs-entries">
        <?php if ($result['entries'] === []) : ?>
        <div class="bk-logs-empty">
            <p class="bk-logs-empty__title"><?php esc_html_e('No entries match these filters', 'wc-buckaroo-bpe-gateway'); ?></p>
            <p><a href="<?php echo esc_url(self::report_url(['log_date' => $date])); ?>"><?php esc_html_e('Show all entries', 'wc-buckaroo-bpe-gateway'); ?></a></p>
        </div>
        <?php else : ?>
            <?php foreach ($result['entries'] as $entry) : ?>
                <?php $this->render_entry($date, $entry); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

        <?php $this->render_pagination($date, $filters, $result['total']); ?>
</div>
        <?php
    }

    public static function report_url(array $args = []): string
    {
        return add_query_arg(array_filter($args, static function ($value) {
            return $value !== '' && $value !== null && $value !== false;
        }), admin_url('admin.php?page=wc-settings&tab=buckaroo_settings&section=report'));
    }

    protected function render_header(bool $debugOn): void
    {
        $storage = LoggerStorage::getStorage();
        $storageLabels = [
            LoggerStorage::STORAGE_FILE => __('Stored in files', 'wc-buckaroo-bpe-gateway'),
            LoggerStorage::STORAGE_DB => __('Stored in the database', 'wc-buckaroo-bpe-gateway'),
            LoggerStorage::STORAGE_ALL => __('Stored in files and the database', 'wc-buckaroo-bpe-gateway'),
        ];
        ?>
    <div class="bk-logs-card bk-logs-header">
        <div class="bk-logs-header__text">
            <h2><?php esc_html_e('Logs', 'wc-buckaroo-bpe-gateway'); ?></h2>
            <p class="description"><?php esc_html_e('What the Buckaroo plugin did, per day: payment requests, responses, pushes and errors. Card data and credentials are left out.', 'wc-buckaroo-bpe-gateway'); ?></p>
            <div class="bk-logs-header__meta">
                <span class="bk-status-pill <?php echo $debugOn ? 'bk-status--live' : 'bk-status--disabled'; ?>">
                    <span class="bk-status-pill-dot"></span>
                    <?php echo esc_html($debugOn ? __('Debug mode on', 'wc-buckaroo-bpe-gateway') : __('Debug mode off', 'wc-buckaroo-bpe-gateway')); ?>
                </span>
                <span class="bk-logs-meta"><?php echo esc_html($storageLabels[$storage] ?? $storageLabels[LoggerStorage::STORAGE_FILE]); ?></span>
                <span class="bk-logs-meta">
                    <?php
                    /* translators: %d: number of days */
                    echo esc_html(sprintf(__('Kept for %d days', 'wc-buckaroo-bpe-gateway'), CronEvents::LOG_STALE_DAYS));
                    ?>
                </span>
            </div>
        </div>
        <div class="bk-logs-header__actions">
            <a class="button button-primary" href="<?php echo esc_url($this->settings_url()); ?>">
                <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e('Log settings', 'wc-buckaroo-bpe-gateway'); ?>
            </a>
        </div>
    </div>
        <?php
    }

    /**
     * Search and filters. WooCommerce wraps the settings in its own form, so these
     * inputs are tied to a form id that does not exist (Enter does not save the
     * settings) and admin-settings.js turns them into a link.
     */
    protected function render_toolbar(string $date, array $filters, int $dayErrors): void
    {
        $base = ['log_date' => $date];
        ?>
    <div class="bk-logs-toolbar" data-bk-logs-search="<?php echo esc_url(self::report_url($base + ['errors' => $filters['errors'] ? '1' : '', 'request' => $filters['request']])); ?>">
        <div class="bk-logs-search">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <label class="screen-reader-text" for="bk-logs-search-input"><?php esc_html_e('Search entries', 'wc-buckaroo-bpe-gateway'); ?></label>
            <input type="search" id="bk-logs-search-input" form="bk-logs-no-form" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Search messages, order numbers, classes…', 'wc-buckaroo-bpe-gateway'); ?>" />
            <button type="button" class="button button-primary" form="bk-logs-no-form"><?php esc_html_e('Search', 'wc-buckaroo-bpe-gateway'); ?></button>
        </div>
        <div class="bk-logs-filters">
            <a class="bk-logs-filter<?php echo $filters['errors'] ? '' : ' is-active'; ?>" href="<?php echo esc_url(self::report_url($base + ['s' => $filters['search'], 'request' => $filters['request']])); ?>"><?php esc_html_e('All entries', 'wc-buckaroo-bpe-gateway'); ?></a>
            <a class="bk-logs-filter<?php echo $filters['errors'] ? ' is-active' : ''; ?>" href="<?php echo esc_url(self::report_url($base + ['s' => $filters['search'], 'request' => $filters['request'], 'errors' => '1'])); ?>">
                <?php esc_html_e('Errors only', 'wc-buckaroo-bpe-gateway'); ?>
                <span class="bk-logs-filter__count"><?php echo esc_html((string) $dayErrors); ?></span>
            </a>
            <?php if ($filters['request'] !== '') : ?>
            <a class="bk-logs-filter is-active bk-logs-filter--removable" href="<?php echo esc_url(self::report_url($base + ['s' => $filters['search'], 'errors' => $filters['errors'] ? '1' : ''])); ?>" title="<?php esc_attr_e('Show all requests', 'wc-buckaroo-bpe-gateway'); ?>">
                <?php
                /* translators: %s: short request id */
                echo esc_html(sprintf(__('Request %s', 'wc-buckaroo-bpe-gateway'), $this->short_request($filters['request'])));
                ?>
                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
            </a>
            <?php endif; ?>
        </div>
    </div>
        <?php
    }

    protected function render_entry(string $date, array $entry): void
    {
        [$summary, $hasMore] = $this->summarize($entry['message']);
        ?>
        <details class="bk-log-entry<?php echo $entry['is_error'] ? ' is-error' : ''; ?>"<?php echo $hasMore ? '' : ' data-single-line'; ?>>
            <summary>
                <span class="bk-log-entry__time"><?php echo esc_html(substr($entry['date'], 11, 8)); ?></span>
                <span class="bk-log-entry__body">
                    <span class="bk-log-entry__source">
                        <?php if ($entry['is_error']) : ?>
                        <span class="bk-logs-badge bk-logs-badge--error"><?php esc_html_e('Error', 'wc-buckaroo-bpe-gateway'); ?></span>
                        <?php endif; ?>
                        <?php if ($entry['location'] !== '') : ?>
                        <code><?php echo esc_html($entry['location']); ?></code>
                        <?php endif; ?>
                        <?php if ($entry['tag'] !== '') : ?>
                        <span class="bk-logs-chip"><?php echo esc_html($entry['tag']); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="bk-log-entry__summary"><?php echo esc_html($summary); ?></span>
                </span>
                <?php if ($entry['request'] !== '') : ?>
                <a class="bk-log-entry__request" href="<?php echo esc_url(self::report_url(['log_date' => $date, 'request' => $entry['request']])); ?>" title="<?php esc_attr_e('Show only this request', 'wc-buckaroo-bpe-gateway'); ?>">#<?php echo esc_html($this->short_request($entry['request'])); ?></a>
                <?php endif; ?>
            </summary>
            <?php if ($hasMore) : ?>
            <pre class="bk-log-entry__message"><?php echo esc_html($entry['message']); ?></pre>
            <?php endif; ?>
        </details>
        <?php
    }

    protected function render_pagination(string $date, array $filters, int $total): void
    {
        $pages = (int) ceil($total / self::PER_PAGE);
        if ($pages <= 1) {
            return;
        }

        $links = paginate_links(
            [
                'base' => self::report_url(['log_date' => $date, 's' => $filters['search'], 'request' => $filters['request'], 'errors' => $filters['errors'] ? '1' : '']) . '&paged=%#%',
                'format' => '',
                'current' => $filters['page'],
                'total' => $pages,
                'prev_text' => '&lsaquo;',
                'next_text' => '&rsaquo;',
            ]
        );

        echo '<nav class="bk-logs-pagination" aria-label="' . esc_attr__('Log pages', 'wc-buckaroo-bpe-gateway') . '">' . wp_kses_post($links) . '</nav>';
    }

    /**
     * @return array{search: string, request: string, errors: bool, page: int}
     */
    protected function get_filters(): array
    {
        $get = static function (string $key): string {
            return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
        };

        return [
            'search' => $get('s'),
            'request' => preg_replace('/[^A-Za-z0-9.]/', '', $get('request')),
            'errors' => $get('errors') === '1',
            'page' => max(1, absint($get('paged'))),
        ];
    }

    /**
     * Logged days, newest first
     *
     * @return array<int, array{date: string, entries: int, errors: int, size: int|null}>
     */
    protected function get_days(): array
    {
        if ($this->uses_file_storage()) {
            $days = [];
            foreach (LoggerStorage::get_log_files() as $date => $file) {
                $entries = $this->read_file_entries($file);
                $days[] = [
                    'date' => $date,
                    'entries' => count($entries),
                    'errors' => count(array_filter(array_column($entries, 'is_error'))),
                    'size' => (int) filesize($file),
                ];
            }

            return $days;
        }

        global $wpdb;
        $wpdb->hide_errors();
        $table = $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE;
        $rows = $wpdb->get_results(
            "SELECT DATE(`date`) AS `day`, COUNT(*) AS `entries`, SUM(`message` REGEXP '^[^[:space:]:]*(Exception|Error): ' OR `location_id` LIKE '%|%error%|%') AS `errors`
            FROM `{$table}` GROUP BY `day` ORDER BY `day` DESC",
            ARRAY_A
        );

        $days = [];
        foreach ($rows ?: [] as $row) {
            $day = DateTime::createFromFormat('!Y-m-d', $row['day']);
            if ($day !== false) {
                $days[] = [
                    'date' => $day->format(LoggerStorage::LOG_DATE_FORMAT),
                    'entries' => (int) $row['entries'],
                    'errors' => (int) $row['errors'],
                    'size' => null,
                ];
            }
        }

        return $days;
    }

    /**
     * Entries of a day after filtering, for the current page
     *
     * @return array{entries: array, total: int, day_entries: int, day_errors: int}|null
     */
    protected function get_entries(string $date, array $filters): ?array
    {
        if ($this->uses_file_storage()) {
            $file = LoggerStorage::get_log_file_path($date);
            if ($file === null) {
                return null;
            }
            $all = array_reverse($this->read_file_entries($file));
        } else {
            $all = $this->read_database_entries($date);
            if ($all === []) {
                return null;
            }
        }

        $matching = array_values(array_filter($all, function (array $entry) use ($filters) {
            if ($filters['errors'] && ! $entry['is_error']) {
                return false;
            }
            if ($filters['request'] !== '' && $entry['request'] !== $filters['request']) {
                return false;
            }
            if ($filters['search'] !== '') {
                $haystack = $entry['message'] . ' ' . $entry['location'] . ' ' . $entry['tag'];

                return stripos($haystack, $filters['search']) !== false;
            }

            return true;
        }));

        return [
            'entries' => array_slice($matching, ($filters['page'] - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => count($matching),
            'day_entries' => count($all),
            'day_errors' => count(array_filter(array_column($all, 'is_error'))),
        ];
    }

    /**
     * Entries of a log file in the order they were written. Messages can span
     * several lines; a line that does not start a new entry belongs to the previous one.
     */
    protected function read_file_entries(string $file): array
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }

        $entries = [];
        $current = null;
        while (($line = fgets($handle)) !== false) {
            if (preg_match(self::FILE_ENTRY_PATTERN, rtrim($line, "\r\n"), $matches)) {
                if ($current !== null) {
                    $entries[] = $this->make_entry(...$current);
                }
                $current = [$matches[1], $matches[2], $matches[3], $matches[4]];
            } elseif ($current !== null) {
                $current[3] .= "\n" . rtrim($line, "\r\n");
            }
        }
        if ($current !== null) {
            $entries[] = $this->make_entry(...$current);
        }
        fclose($handle);

        return $entries;
    }

    /**
     * Entries of a day from the database, newest first
     */
    protected function read_database_entries(string $date): array
    {
        global $wpdb;
        $wpdb->hide_errors();
        $table = $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE;
        $day = DateTime::createFromFormat('!' . LoggerStorage::LOG_DATE_FORMAT, $date);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `date`, `process_id`, `location_id`, `message` FROM `{$table}` WHERE `date` >= %s AND `date` < %s ORDER BY `id` DESC",
                $day->format('Y-m-d 00:00:00'),
                (clone $day)->modify('+1 day')->format('Y-m-d 00:00:00')
            ),
            ARRAY_A
        );

        return array_map(function (array $row) {
            return $this->make_entry($row['date'], $row['process_id'], $row['location_id'], $row['message']);
        }, $rows ?: []);
    }

    /**
     * Split "Class::method|tag|" into a readable location and tag
     */
    protected function make_entry(string $date, string $request, string $location, string $message): array
    {
        $parts = explode('|', $location);
        $place = str_replace('Buckaroo\\Woocommerce\\', '', trim($parts[0]));
        $tag = trim($parts[1] ?? '');

        // Numeric tags (|1|, |10|) only mark a spot in the code
        if (ctype_digit($tag)) {
            $tag = '';
        }

        return [
            'date' => $date,
            'request' => trim($request),
            'location' => $place,
            'tag' => $tag,
            'message' => rtrim($message),
            'is_error' => preg_match(self::ERROR_MESSAGE_PATTERN, ltrim($message)) === 1 || stripos($tag, 'error') !== false,
        ];
    }

    /**
     * First line of a message for the collapsed row, and whether there is more to show
     *
     * @return array{0: string, 1: bool}
     */
    protected function summarize(string $message): array
    {
        $message = trim($message);
        $firstLine = strtok($message, "\n");
        $firstLine = $firstLine === false ? '' : $firstLine;

        // An exception's "in /path/file.php:12" is noise in the summary; it is still in the full message
        $firstLine = preg_replace('#^([^\s:]*(?:Exception|Error): .*?) in (?:/|[a-z]+://)\S.*:\d+$#', '$1', $firstLine);
        $summary = mb_strlen($firstLine) > 220 ? mb_substr($firstLine, 0, 220) . '…' : $firstLine;

        return [$summary, $summary !== $message];
    }

    protected function uses_file_storage(): bool
    {
        return LoggerStorage::getStorage() !== LoggerStorage::STORAGE_DB;
    }

    protected function settings_url(): string
    {
        return admin_url('admin.php?page=wc-settings&tab=buckaroo_settings&section=advanced');
    }

    protected function format_day(string $date, bool $withYear = false): string
    {
        $day = DateTime::createFromFormat('!' . LoggerStorage::LOG_DATE_FORMAT, $date);
        if ($day === false) {
            return $date;
        }

        if ($withYear) {
            $format = 'l j F Y';
        } else {
            $format = $day->format('Y') !== gmdate('Y') ? 'D j F Y' : 'D j F';
        }

        return date_i18n($format, (int) $day->format('U') + 12 * HOUR_IN_SECONDS, true);
    }

    protected function relative_day(string $date): string
    {
        $today = date(LoggerStorage::LOG_DATE_FORMAT);
        if ($date === $today) {
            return __('Today', 'wc-buckaroo-bpe-gateway');
        }

        return $date === date(LoggerStorage::LOG_DATE_FORMAT, strtotime('-1 day')) ? __('Yesterday', 'wc-buckaroo-bpe-gateway') : '';
    }

    protected function count_label(int $entries, int $errors): string
    {
        /* translators: %d: number of log entries */
        $label = sprintf(_n('%d entry', '%d entries', $entries, 'wc-buckaroo-bpe-gateway'), $entries);

        if ($errors > 0) {
            /* translators: %d: number of errors */
            $label .= ' · ' . sprintf(_n('%d error', '%d errors', $errors, 'wc-buckaroo-bpe-gateway'), $errors);
        }

        return $label;
    }

    protected function short_request(string $request): string
    {
        // uniqid('', true) ids share their first characters within a day; the end differs
        return substr(str_replace('.', '', $request), -6);
    }
}
