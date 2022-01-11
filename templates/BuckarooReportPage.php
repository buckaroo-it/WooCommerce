<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The Template for displaying reports
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */
class BuckarooReportPage extends WP_List_Table
{

    const FILE_LOCATION = '/library/api/log/report_log.txt';

    /**
     * Total items in file
     *
     * @var int
     */
    protected $total_items = 0;
    /**
     * Items per page
     *
     * @var integer
     */
    protected $per_page = 20;
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(
            array(
                'singular' => __('Log'),
                'plural'   => __('Logs'),
                'ajax'     => false,
            )
        );
        $this->getTotalItems();
    }

    /**
     * No items found text.
     */
    public function no_items()
    {
        _e('No log data found.', 'wc-buckaroo-bpe-gateway');
    }

    /**
     * Output the report.
     */
    public function output_report()
    {
        $this->prepare_items();
        echo '<div id="buckaroo-report" class="woocommerce-reports-wide">';
        $this->display();
        echo '</div>';
    }
    /**
     * Get page items
     *
     * @param int $current_page Current page
     *
     * @return array $items
     */
    public function get_items($current_page) 
    {
        
        if (!file_exists($this->getFilePath())) {
            return [];
        }
        $items = [];

        $file = new \SplFileObject($this->getFilePath(), 'r');
        $file->seek($this->getStartingLine($current_page));

        $count =  $this->per_page;
        if ($current_page * $this->per_page > $this->total_items) {
            $count =  $this->total_items % $this->per_page;
        }
        
        for ($i=1; $i <= $count; $i++) { 
            if (!$file->eof()) {
                if ($this->isNotLastItem($current_page, $i)) {
                    $items[] = $this->formatLine(
                        $this->getColumnIndex($i, $current_page),
                        $file->current()
                    );
                }
            }
            $file->next();
        }
        
        return array_reverse($items);
    }
    /**
     * Determine if not the last row in file
     *
     * @param int $current_page
     * @param int $i
     *
     * @return boolean
     */
    public function isNotLastItem($current_page ,$i)
    {
        return (($current_page -1)* $this->per_page) + $i <= $this->total_items;
    }
    /**
     * Get index of row in page
     *
     * @param int $i            Current index
     * @param int $current_page Current page
     *
     * @return void
     */
    protected function getColumnIndex($i, $current_page)
    {
        $pageColumnStartIndex = (($current_page - 1) * $this->per_page);
        $leftOnThePage = $this->per_page;
        if (($pageColumnStartIndex  + $this->per_page) > $this->total_items) {
            $leftOnThePage =  $this->total_items % $this->per_page;
        }
        return $pageColumnStartIndex  + ($leftOnThePage - $i + 1);
    }
    /**
     * Format file line
     *
     * @param string $index Line index
     * @param string $line  File line
     *
     * @return array 
     */
    protected function formatLine($index, $line)
    {
        $index = $index;
        
        $tmp = explode("|||", $line);

        if (count($tmp) > 1) {
            list ($date, $description) = $tmp;
        } else {
            $date = 'unknown';
            $description = $line;
        }
        return compact('index', 'date', 'description');
    }
    /**
     * Get column value.
     *
     * @param mixed  $item
     * @param string $column_name
     */
    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    /**
     * Get columns.
     *
     * @return array
     */
    public function get_columns()
    {
        $columns = array(
            'index'      => __('Error no', 'wc-buckaroo-bpe-gateway'),
            'date'       => __('Date', 'wc-buckaroo-bpe-gateway'),
            'description'  => __('Description', 'wc-buckaroo-bpe-gateway'),
        );

        return $columns;
    }
    /**
     * Prepare report list items.
     */
    public function prepare_items()
    {
        $this->_column_headers = array( $this->get_columns(), array(),array());
        $current_page          = absint($this->get_pagenum());

        $this->items = $this->get_items($current_page);

        /**
         * Pagination.
         */
        $this->set_pagination_args(
            array(
                'total_items' => $this->total_items,
                'per_page'    => $this->per_page,
                'total_pages' => ceil($this->total_items / $this->per_page),
            )
        );
    }
    /**
     * Get total lines from file
     *
     */
    protected function getTotalItems() 
    {
        if (!file_exists($this->getFilePath())) {
            return;
        }
        $file = new \SplFileObject($this->getFilePath(), 'r');
        $file->seek(PHP_INT_MAX);

        $this->total_items = $file->key();
    }
    /**
     * Get the path to the file
     *
     * @return string
     */
    protected function getFilePath()
    {
        return dirname(BK_PLUGIN_FILE).self::FILE_LOCATION;
    }
    /**
     * Get page starting line
     *
     * @param int $current_page Current page
     *
     * @return int $startingLine
     */
    protected function getStartingLine($current_page)
    {
        $startingLine = $this->total_items - ($current_page * $this->per_page);
        return $startingLine > 0 ? $startingLine : 0;
    }
}
