<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WP_List_Table' ) ) {
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
class Buckaroo_Report_Page extends WP_List_Table {


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

	protected $file_raport_lines = array();

	protected $file_starting_line = array();
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => esc_html__( 'Log' ),
				'plural'   => esc_html__( 'Logs' ),
				'ajax'     => false,
			)
		);
		$this->set_total_items_count();
	}

	/**
	 * No items found text.
	 */
	public function no_items() {
		esc_html__( 'No log data found.', 'wc-buckaroo-bpe-gateway' );
	}

	/**
	 * Output the report.
	 */
	public function output_report() {
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
	public function get_items( $current_page ) {
		return $this->get_items_from_storage( $current_page );
	}

	/**
	 * Get column value.
	 *
	 * @param mixed  $item
	 * @param string $column_name
	 */
	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'index'       => esc_html__( 'Error no', 'wc-buckaroo-bpe-gateway' ),
			'date'        => esc_html__( 'Date', 'wc-buckaroo-bpe-gateway' ),
			'description' => esc_html__( 'Description', 'wc-buckaroo-bpe-gateway' ),
		);

		return $columns;
	}
	/**
	 * Prepare report list items.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$current_page          = absint( $this->get_pagenum() );

		$this->items = $this->get_items( $current_page );
		/**
		 * Pagination.
		 */
		$this->set_pagination_args(
			array(
				'total_items' => $this->total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $this->total_items / $this->per_page ),
			)
		);
	}
	/**
	 * Get total lines from file
	 */
	protected function set_total_items_count() {
		$this->total_items = $this->get_total_items_count_for_storage();
	}
	/**
	 * Get total count for database storage
	 *
	 * @return void
	 */
	public function get_total_items_count_for_storage() {
		$storage = BuckarooConfig::get( 'logstorage' ) ?? Buckaroo_Logger_Storage::STORAGE_FILE;
		if ( strlen( $storage ) === 0 || $storage === Buckaroo_Logger_Storage::STORAGE_ALL ) {
			$storage = Buckaroo_Logger_Storage::STORAGE_FILE;
		}
		$method = 'get_total_count_' . $storage;
		if ( method_exists( $this, $method ) ) {
			return $this->{$method}();
		}
		return 0;
	}
	/**
	 * Get total count for file storage
	 *
	 * @return void
	 */
	public function get_total_count_database() {
		global $wpdb;
		$wpdb->hide_errors();

		$table  = $wpdb->prefix . Buckaroo_Logger_Storage::STORAGE_DB_TABLE;
		$result = $wpdb->get_results(
			'SELECT count(`id`) as `count` FROM `' . $table . '`',
			ARRAY_A
		);

		if ( $result !== null && count( $result ) ) {
			return (int) $result[0]['count'];
		}

		return 0;
	}
	/**
	 * Get items for current page from selected storage
	 *
	 * @return void
	 */
	public function get_items_from_storage( $current_page ) {
		$storage = BuckarooConfig::get( 'logstorage' ) ?? Buckaroo_Logger_Storage::STORAGE_FILE;
		$method  = $this->get_storage_method( $storage );

		if ( method_exists( $this, $method ) ) {
			return $this->{$method}( $current_page );
		}
		return array();
	}
	/**
	 * Get method name for logger storage
	 *
	 * @param string $storage
	 *
	 * @return string
	 */
	protected function get_storage_method( $storage ) {
		if ( strlen( $storage ) === 0 || $storage === Buckaroo_Logger_Storage::STORAGE_ALL ) {
			$storage = Buckaroo_Logger_Storage::STORAGE_FILE;
		}
		return 'get_page_item_from_' . $storage;
	}
	/**
	 * Get items for current page from file storage
	 *
	 * @param int $current_page
	 *
	 * @return array
	 */
	protected function get_page_item_from_file( $current_page ) {

		$directory = Buckaroo_Logger_Storage::get_file_storage_location();
		$logs      = glob( $directory . '*.log' );

		$items = array();

		foreach ( $logs as $fileName ) {
			$date = 'unkown';
			try {
				$date = DateTime::createFromFormat(
					'd-m-Y',
					str_replace( '.log', '', basename( $fileName ) )
				);

			} catch ( \Throwable $th ) {
				Buckaroo_Logger::log( __METHOD__, 'Invalid file name for log: ' . $fileName );
			}
			$items[] = array(
				'date'        => $date,
				'description' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=buckaroo_settings&section=logs&log_file=' . basename( $fileName ) ) ) . '">' . basename( $fileName ) . '</a>',
			);
		}

		// sort by date
		usort(
			$items,
			function ( $item1, $item2 ) {
				return $item1['date'] < $item2['date'];
			}
		);

		$itemsWithIndex = array();

		foreach ( $items as $key => $item ) {
			$item['index'] = $key + 1;
			if ( $item['date'] instanceof DateTime ) {
				$item['date'] = $item['date']->format( 'd-m-Y' );
			}
			$itemsWithIndex[] = $item;
		}
		$pages = array_chunk( $itemsWithIndex, $this->per_page );

		if ( isset( $pages[ $current_page - 1 ] ) ) {
			return $pages[ $current_page - 1 ];
		}

		return array();
	}
	public function display_log_file( $fileName ) {
		$backButton     = '<a style="margin-right:10px" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=buckaroo_settings&section=report' ) ) . '">' . esc_html__( 'Back' ) . '</a>';
		$downloadButton = '<a style="margin-left:10px" href="' . esc_url( plugin_dir_url( BK_PLUGIN_FILE ) . '?buckaroo_download_log_file=' . $fileName ) . '">' . esc_html__( 'Download' ) . '</a>';
		$directory      = Buckaroo_Logger_Storage::get_file_storage_location();
		$logs           = glob( $directory . '*.log' );

		$logData = '<p>' . $backButton . esc_html__( 'No log file found' ) . '</p>';
		foreach ( $logs as $filePath ) {
			if ( basename( $filePath ) === $fileName ) {
				$file    = file_get_contents( $filePath );
				$logData = '<h4>' . $backButton . $fileName . $downloadButton . "</h4></hr><textarea disabled style='width:100%;height:80vh;'>" . htmlentities( $file ) . '</textarea>';
			}
		}
		echo wp_kses(
			$logData,
			array(
				'a'        => array(
					'style' => true,
					'href'  => true,
				),
				'p'        => array(),
				'h4'       => array(),
				'hr'       => array(),
				'textarea' => array(
					'style'    => true,
					'disabled' => true,
				),
			)
		);
	}
	/**
	 * Get total count for file storage
	 *
	 * @return void
	 */
	public function get_total_count_file() {
		$directory = Buckaroo_Logger_Storage::get_file_storage_location();
		$logs      = glob( $directory . '*.log' );
		return count( $logs );
	}

	protected function get_page_item_from_database( $current_page ) {
		global $wpdb;
		$wpdb->hide_errors();

		$table   = $wpdb->prefix . Buckaroo_Logger_Storage::STORAGE_DB_TABLE;
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `date`, `message` as `description` FROM {$table} ORDER BY `date` DESC LIMIT %d,%d",
				( $this->per_page * ( $current_page - 1 ) ),
				$this->per_page
			),
			ARRAY_A
		);
		$results = array();
		if ( $rows !== null ) {
			foreach ( $rows as $key => $row ) {
				$row['index']       = ( $key + 1 ) + ( 10 * ( $current_page - 1 ) );
				$row['description'] = "<code style='display:block;padding:10px;'><pre>" . htmlentities( $row['description'] ) . '</pre></code>';
				$results[]          = $row;
			}
		}
		return $results;
	}
}
