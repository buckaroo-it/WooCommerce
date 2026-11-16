<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Services\LoggerStorage;

/**
 * Lets shop managers download a day's log file from the Reports page.
 */
class ReportDownload
{
    public const ACTION = 'buckaroo_download_log';

    public const CAPABILITY = 'manage_woocommerce';

    public function __construct()
    {
        // admin-post.php only runs for logged-in users
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * Download link for the log of a day (d-m-Y)
     */
    public static function url(string $date): string
    {
        return wp_nonce_url(
            add_query_arg(['action' => self::ACTION, 'date' => $date], admin_url('admin-post.php')),
            self::ACTION
        );
    }

    public function handle(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to download Buckaroo logs.', 'wc-buckaroo-bpe-gateway'), '', ['response' => 403]);
        }

        check_admin_referer(self::ACTION);

        $date = isset($_GET['date']) && is_string($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : '';

        LoggerStorage::downloadFile($date);
    }
}
