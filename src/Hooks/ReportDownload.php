<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Services\LoggerStorage;
use WP_Error;

class ReportDownload
{
    public const ACTION = 'buckaroo_download_log';

    public const FILE_PARAMETER = 'log_file';

    public function __construct()
    {
        add_action('admin_post_' . self::ACTION, [$this, 'download']);
    }

    /**
     * Admin URL that downloads a log file
     *
     * @return string
     */
    public static function get_download_url(string $fileName)
    {
        return add_query_arg(
            [
                'action' => self::ACTION,
                self::FILE_PARAMETER => rawurlencode($fileName),
                '_wpnonce' => wp_create_nonce(self::ACTION),
            ],
            admin_url('admin-post.php')
        );
    }

    /**
     * Send the requested log file, or stop with an error status
     *
     * @return void
     */
    public function download()
    {
        $file = $this->get_requested_file();

        if (is_wp_error($file)) {
            wp_die(
                esc_html($file->get_error_message()),
                '',
                ['response' => (int) $file->get_error_data()]
            );
        }

        LoggerStorage::send_file($file);
        exit;
    }

    /**
     * Path of the requested log file when the current user may download it
     *
     * @return string|WP_Error
     */
    public function get_requested_file()
    {
        if (! is_user_logged_in() || ! current_user_can('manage_woocommerce')) {
            return new WP_Error('forbidden', __('You are not allowed to download log files.', 'wc-buckaroo-bpe-gateway'), 403);
        }

        $nonce = isset($_GET['_wpnonce']) && is_string($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, self::ACTION)) {
            return new WP_Error('invalid_nonce', __('The link has expired. Please reload the page and try again.', 'wc-buckaroo-bpe-gateway'), 403);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Must match the log file pattern below.
        $fileName = isset($_GET[self::FILE_PARAMETER]) && is_string($_GET[self::FILE_PARAMETER]) ? wp_unslash($_GET[self::FILE_PARAMETER]) : '';

        if (LoggerStorage::get_log_file_date($fileName) === null) {
            return new WP_Error('invalid_file', __('Invalid log file.', 'wc-buckaroo-bpe-gateway'), 400);
        }

        $file = LoggerStorage::get_log_file_path($fileName);

        if ($file === null) {
            return new WP_Error('not_found', __('No log file found.', 'wc-buckaroo-bpe-gateway'), 404);
        }

        return $file;
    }
}
