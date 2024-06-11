<?php
/**
 * Post Types Admin
 *
 * @package  WooCommerce/admin
 * @version  3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'BK_Admin_Post_Types', false ) ) {
	new BK_Admin_Post_Types();
	return;
}

/**
 * BK_Admin_Post_Types Class.
 *
 * Handles the edit posts views and some functionality on the edit post screen for WC post types.
 */
class BK_Admin_Post_Types {

	/**
	 * Constructor.
	 */
	public function __construct() {
		include_once __DIR__ . '/class-wc-admin-meta-boxes.php';

		add_filter( 'default_hidden_meta_boxes', array( $this, 'hidden_meta_boxes' ), 10, 2 );
	}

	/**
	 * Disable the auto-save functionality for Orders.
	 */
	public function disable_autosave() {
		global $post;

		if ( $post && in_array( get_post_type( $post->ID ), wc_get_order_types( 'order-meta-boxes' ), true ) ) {
			wp_dequeue_script( 'autosave' );
		}
	}

	/**
	 * Hidden default Meta-Boxes.
	 *
	 * @param  array  $hidden Hidden boxes.
	 * @param  object $screen Current screen.
	 * @return array
	 */
	public function hidden_meta_boxes( $hidden, $screen ) {
		if ( 'product' === $screen->post_type && 'post' === $screen->base ) {
			$hidden = array_merge( $hidden, array( 'postcustom' ) );
		}

		return $hidden;
	}
}

new BK_Admin_Post_Types();
