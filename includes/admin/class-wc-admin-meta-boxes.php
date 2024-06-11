<?php
/**
 * Buckaroo Meta Boxes
 *
 * Sets up the write panels used by products and orders (custom post types).
 *
 * @author      9Yards
 * @category    Admin
 * @package     WooCommerce/Admin/Meta Boxes
 * @version     2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * BK_Admin_Meta_Boxes.
 */
class BK_Admin_Meta_Boxes {

	/**
	 * Is meta boxes saved once?
	 *
	 * @var boolean
	 */
	private static $saved_meta_boxes = false;

	/**
	 * Meta box error messages.
	 *
	 * @var array
	 */
	public static $meta_box_errors = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes_shop_order', array( $this, 'add_meta_boxes' ), 30 );

		/**
		 * Save Order Meta Boxes.
		 *
		 * In order:
		 *      Save the order items.
		 *      Save the order totals.
		 *      Save the order downloads.
		 *      Save order data - also updates status and sends out admin emails if needed. Last to show latest data.
		 *      Save actions - sends out other emails. Last to show latest data.
		 */
		add_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Items::save', 10, 2 );
		add_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Downloads::save', 30, 2 );
		add_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2 );
		add_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Actions::save', 50, 2 );

		// Save Product Meta Boxes.
		add_action( 'woocommerce_process_product_meta', 'WC_Meta_Box_Product_Data::save', 10, 2 );
		add_action( 'woocommerce_process_product_meta', 'WC_Meta_Box_Product_Images::save', 20, 2 );

		// Save Coupon Meta Boxes.
		add_action( 'woocommerce_process_shop_coupon_meta', 'WC_Meta_Box_Coupon_Data::save', 10, 2 );

		// Save Rating Meta Boxes.
		add_filter( 'wp_update_comment_data', 'WC_Meta_Box_Product_Reviews::save', 1 );

		// Error handling (for showing errors from meta boxes on next page load).
		add_action( 'admin_notices', array( $this, 'output_errors' ) );
		add_action( 'shutdown', array( $this, 'save_errors' ) );
	}

	/**
	 * Add an error message.
	 *
	 * @param string $text
	 */
	public static function add_error( $text ) {
		self::$meta_box_errors[] = $text;
	}

	/**
	 * Save errors to an option.
	 */
	public function save_errors() {
		update_option( 'woocommerce_meta_box_errors', self::$meta_box_errors );
	}

	/**
	 * Show any stored error messages.
	 */
	public function output_errors() {
		$errors = array_filter( (array) get_option( 'woocommerce_meta_box_errors' ) );

		if ( ! empty( $errors ) ) {

			echo '<div id="woocommerce_errors" class="error notice is-dismissible">';

			foreach ( $errors as $error ) {
				echo '<p>' . wp_kses_post( $error ) . '</p>';
			}

			echo '</div>';

			// Clear
			delete_option( 'woocommerce_meta_box_errors' );
		}
	}

	/**
	 * Add WC Meta boxes.
	 */
	public function add_meta_boxes( $post ) {
		$screen = get_current_screen();
		$order  = wc_get_order( $post->ID );
		if ( $order->get_payment_method() === 'buckaroo_klarnakp' ) {
			return;
		}
		// Orders.
		foreach ( wc_get_order_types( 'order-meta-boxes' ) as $type ) {
			$order_type_object = get_post_type_object( $type );
			add_meta_box( 'buckaroo-order-capture', __( 'Capture order', 'woocommerce' ), 'BK_Meta_Box_Order_Items::output', $type, 'normal', 'low' );
		}
	}
}

new BK_Admin_Meta_Boxes();
