<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * BK_Meta_Box_Order_Items Class.
 */
class BK_Meta_Box_Order_Items {

	/**
	 * Output the metabox.
	 *
	 * @param WP_Post $post
	 */
	public static function output( $post ) {
		global $post, $thepostid, $theorder;

		if ( ! is_int( $thepostid ) ) {
			$thepostid = $post->ID;
		}

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $thepostid );
		}

		$order = $theorder;
		$data  = get_post_meta( $post->ID );

		if ( get_post_meta( $order->get_id(), '_wc_order_authorized', true ) ) {
			include 'views/html-order-capture.php';
		} else {
			include 'views/html-order-capture-blocked.php';
		}
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id
	 */
	public static function save( $post_id ) {
		/**
		 * This $_POST variable's data has been validated and escaped
		 * inside `wc_save_order_items()` function.
		 */
		wc_save_order_items( $post_id, $_POST );
	}
}
