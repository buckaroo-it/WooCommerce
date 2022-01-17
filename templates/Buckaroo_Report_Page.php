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
class Buckaroo_Report_Page extends WP_List_Table
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
    protected $per_page = 3;

    protected $file_raport_lines = [];

    protected $file_starting_line = [];
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
        $this->set_total_items_count();
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
        echo '<style>#date {min-width:130px;} #index {min-width:30px;}</style><div id="buckaroo-report" class="woocommerce-reports-wide">';
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
        Buckaroo_Logger::log(__METHOD__, $this);
        return $this->get_items_from_storage($current_page);
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
    protected function set_total_items_count() 
    {
        $this->total_items = $this->get_total_items_count_for_storage();
    }
    /**
     * Get total count for database storage
     *
     * @return void
     */
    public function get_total_items_count_for_storage()
    {
        $storage = BuckarooConfig::get('logstorage') ?? Buckaroo_Logger_Storage::STORAGE_FILE;
        if (strlen($storage) === 0 || $storage === Buckaroo_Logger_Storage::STORAGE_ALL) {
            $storage = Buckaroo_Logger_Storage::STORAGE_FILE;
        }
        $method = "get_total_count_".$storage;
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }
        return 0;
    }
    /**
     * Get total count for file storage
     *
     * @return void
     */
    public function get_total_count_database()
    {
        global $wpdb;
        $wpdb->hide_errors();

        $table = $wpdb->prefix.Buckaroo_Logger_Storage::STORAGE_DB_TABLE;
        $result = $wpdb->get_results(
            "SELECT count(`id`) as `count` FROM `". $table . "`",
            ARRAY_A
        );
        
        if ($result !== null && count($result)) {
            return (int)$result[0]['count'];
        }

        return 0;
    }
    /**
     * Get items for current page from selected storage
     *
     * @return void
     */
    public function get_items_from_storage($current_page)
    {
        $storage = BuckarooConfig::get('logstorage') ?? Buckaroo_Logger_Storage::STORAGE_FILE;
        $method = $this->get_storage_method($storage);
        
        if (method_exists($this, $method)) {
            return $this->{$method}($current_page);
        }
        return [];
    }
    /**
     * Get method name for logger storage
     *
     * @param string $storage
     *
     * @return string
     */
    protected function get_storage_method($storage)
    {
        if (strlen($storage) === 0 || $storage === Buckaroo_Logger_Storage::STORAGE_ALL) {
            $storage = Buckaroo_Logger_Storage::STORAGE_FILE;
        }
        return 'get_page_item_from_'.$storage;
    }
    /**
     * Get items for current page from file storage
     *
     * @param int $current_page
     *
     * @return array
     */
    protected function get_page_item_from_file($current_page)
    {
        if (!file_exists($this->get_file_path())) {
            return array();
        }

        $file = new \SplFileObject($this->get_file_path(), 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $file->seek(PHP_INT_MAX);
        $endFile = $file->key();

        $pages = array_chunk(array_reverse($this->file_raport_lines), $this->per_page);
        
        if ($current_page === 1) {
            $filePageStart = $endFile;
        } else {
            $filePageStart = $pages[$current_page-1][0];
        }
        if ($current_page === count($pages)) {
            $filePageEnd = 0;
        } else {
            $filePageEnd =  $pages[$current_page][0];
        }

        $fileLine = $filePageStart;

        $i = 0;
        $index = 0;
        $raportItems = [];

        while ($fileLine > $filePageEnd) {
            $i++;
            $fileLine = $filePageStart - $i;
            $file->seek($fileLine);
            $currentLine = $file->key();

            $lineData  = $file->current();
            $lineData = str_replace("-->", "", $lineData);
            $raportItem[] = $lineData;
            
            if (in_array($currentLine, $this->file_raport_lines) || $currentLine === 0) {
                $raportItems[] = $this->format_file_line(
                    $this->get_column_index($index++, $current_page),
                    implode("<br>", array_reverse($raportItem))
                );
                
                $raportItem = [];
            }
        }
        
        return $raportItems;
    }
    /**
     * Get total count for file storage
     *
     * @return void
     */
    public function get_total_count_file()
    {
        if (!file_exists($this->get_file_path())) {
            return 0;
        }
        $file = new \SplFileObject($this->get_file_path(), 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);
        $lines = [];

        while (!$file->eof()) {
            $rowData = $file->current();
            $key = $file->key();
            if (str_contains($rowData, "-->")) {
                $lines[] = $key;
            }
            $file->next();
        }
        $lines[] =  $file->key();
        $this->file_raport_lines = $lines;
        return count($lines);
    }

    protected function get_page_item_from_database($current_page)
    {
        global $wpdb;
        $wpdb->hide_errors();

        $table = $wpdb->prefix.Buckaroo_Logger_Storage::STORAGE_DB_TABLE;
        $rows = $wpdb->get_results(
            "SELECT `date`, `message` as `description` FROM `". $table . "` ORDER BY `date` DESC LIMIT ". ($this->per_page * ($current_page - 1)) .",".$this->per_page,
            ARRAY_A
        );
        $results = [];
        if ($rows !== null) {
            foreach ($rows as $key => $row) {
                $row['index'] = ($key + 1) + (10 * ($current_page - 1));
                $row['description'] = htmlentities($row['description']);
                $results[] = $row;
            }
        }
        return $results;
    }
    /**
     * Get index of row in page
     *
     * @param int $i            Current index
     * @param int $current_page Current page
     *
     * @return void
     */
    protected function get_column_index($i, $current_page)
    {
        return $i + (($current_page - 1) * $this->per_page) + 1;
    }
    /**
     * Format file line
     *
     * @param string $index Line index
     * @param string $line  File line
     *
     * @return array 
     */
    protected function format_file_line($index, $line)
    {
        $index = $index;
        
        $tmp = explode("|||", $line);

        if (count($tmp) > 1) {
            list ($date, $description) = $tmp;
            $description = implode("", array_slice($tmp, 1));
        } else {
            $date = 'unknown';
            $description = $line;
        }

        $description = htmlentities($description);

        return compact('index', 'date', 'description');
    }  
    /**
     * Get the path to the file
     *
     * @return string
     */
    protected function get_file_path()
    {
        return Buckaroo_Logger_Storage::get_file_location();
    }
}
