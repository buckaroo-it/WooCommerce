<?php

/**
 * Core class for order capture
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

class Buckaroo_Order_Capture {

	/**
	 * @var Buckaroo_Order_Details
	 */
	protected $order_details;

	/**
	 * @var Buckaroo_Http_Request
	 */
	protected $request;

	/**
	 * @var  Buckaroo_Order_Item[]
	 */
	protected $form_items;

	private $item_qtys;
	private $item_totals;
	private $item_tax_totals;

	/**
	 * @var Buckaroo_Capture_Transaction[]
	 */
	protected $previous_captures = array();


	public function __construct( Buckaroo_Order_Details $order_details, Buckaroo_Http_Request $request ) {
		$this->order_details = $order_details;
		$this->request       = $request;
		$this->init_form_inputs();
		$this->init_form_items();
		$this->init_previous_captures();
	}

	/**
	 * Get order details
	 *
	 * @return Buckaroo_Order_Details
	 */
	public function get_order_details() {
		return $this->order_details;
	}

	/**
	 * Init previous captures
	 *
	 * @return void
	 */
	public function init_previous_captures() {
		$previous_captures = $this->order_details->get_meta( '_wc_order_captures' );

		if ( ! is_array( $previous_captures ) ) {
			return array();
		}
		$this->previous_captures = array_map(
			function ( $capture_transaction ) {
				return new Buckaroo_Capture_Transaction( $capture_transaction, $this->order_details->get_order() );
			},
			$previous_captures
		);
	}

	/**
	 * Get previous captures
	 *
	 * @return array
	 */
	public function get_previous_captures() {
		return $this->previous_captures;
	}

	/**
	 * Get items available for capture
	 *
	 * @return Buckaroo_Item_For_Capture[]
	 */
	public function get_available_to_capture() {
		$available_items = array();
		$order_items     = $this->order_details->get_items_for_capture();
		foreach ( $order_items as $order_item ) {
			$available_items[] = new Buckaroo_Item_For_Capture(
				$order_item,
				$this->get_previous_capture_with_item( $order_item )
			);
		}

		return array_filter(
			$available_items,
			function ( $item ) {
				return $item->is_available_for_capture();
			}
		);
	}

	/**
	 * Get transactions that have item
	 *
	 * @param Buckaroo_Order_Item $item
	 *
	 * @return Buckaroo_Capture_Transaction[]
	 */
	protected function get_previous_capture_with_item( Buckaroo_Order_Item $item ) {
		return array_filter(
			$this->previous_captures,
			function ( $capture_transaction ) use ( $item ) {
				return $capture_transaction->has_item( $item->get_line_item_id() );
			}
		);
	}
	/**
	 * Get item qty from form
	 *
	 * @param int $item_id
	 *
	 * @return int
	 */
	public function get_item_qty( int $item_id ) {
		$qty = $this->get_input_item_value( $this->item_qtys, $item_id );
		if ( $qty === null ) {
			$qty = 1;
		}
		return (int) $qty;
	}

	/**
	 * Get item total from form
	 *
	 * @param integer $item_id
	 *
	 * @return float
	 */
	public function get_item_total( int $item_id ) {
		return (float) $this->get_input_item_value( $this->item_totals, $item_id );
	}

	/**
	 * Get item tax total from form
	 *
	 * @param integer $item_id
	 *
	 * @return array|null
	 */
	public function get_item_tax_totals( int $item_id ) {
		return $this->get_input_item_value( $this->item_tax_totals, $item_id );
	}

	/**
	 * Get form items
	 *
	 * @return Buckaroo_Order_Item[]
	 */
	public function get_form_items() {
		return $this->form_items;
	}

	/**
	 * Get qty/totals/tax value for item with item id,
	 * returns 0 if not found
	 *
	 * @param array   $item_list
	 * @param integer $item_id
	 *
	 * @return float|null
	 */
	private function get_input_item_value( array $item_list, int $item_id ) {
		if ( isset( $item_list[ $item_id ] ) ) {
			return $item_list[ $item_id ];
		}
	}

	/**
	 * Sanitize inputs and store them in private properties
	 *
	 * @return void
	 */
	private function init_form_inputs() {
		$this->item_qtys       = $this->sanitize_json( 'line_item_qtys' );
		$this->item_totals     = $this->sanitize_json( 'line_item_totals' );
		$this->item_tax_totals = $this->sanitize_json( 'line_item_tax_totals' );
	}
	/**
	 * Init order items from item ids
	 *
	 * @return void
	 */
	private function init_form_items() {
		$input_item_ids = array_keys( $this->item_totals );
		$form_items     = array_map(
			function ( $itemId ) {
				return $this->order_details->get_item( $itemId );
			},
			$input_item_ids
		);

		$this->form_items = array_filter(
			$form_items,
			function ( $item ) {
				return $item !== null;
			}
		);
	}
	/**
	 * Convert $_POST json string to array and sanitize it
	 *
	 * @param string $key
	 *
	 * @return array
	 */
	private function sanitize_json( $key ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			return array();
		}

		$result = json_decode( wp_unslash( $_POST[ $key ] ), true );
		if ( ! is_array( $result ) ) {
			return array();
		}

		return map_deep(
			$result,
			'sanitize_text_field'
		);
	}
}
