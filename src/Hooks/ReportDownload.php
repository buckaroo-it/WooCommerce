<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Services\LoggerStorage;

class ReportDownload
{
    public function __construct()
    {
        if (isset($_GET['buckaroo_download_log_file']) && is_string($_GET['buckaroo_download_log_file'])) {
            $report_name = preg_replace('/[^A-Za-z0-9-.]+/', '-', $_GET['buckaroo_download_log_file']);
            LoggerStorage::downloadFile($report_name);
        }
    }
}
