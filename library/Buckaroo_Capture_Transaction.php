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

class Buckaroo_Capture_Transaction {


	/**
	 * @var Buckaroo_Order_Item[]
	 */
	protected $items = array();

	/**
	 * @var WC_Order
	 */
	protected $order;

	protected $item_ids = array();

	public function __construct( array $data, WC_Order $order ) {
		$this->data  = $data;
		$this->order = $order;
		$this->init_items();
	}

	public function get_id() {
		return $this->data['id'];
	}
	public function get_total_amount() {
		return $this->data['amount'];
	}

	public function has_item( int $item_id ) {
		return in_array( $item_id, $this->item_ids );
	}
	public function get_currency() {
		return $this->data['currency'];
	}

	public function get_transaction_id() {
		return $this->data['transaction_id'] ?? null;
	}
	/**
	 * Get item qty
	 *
	 * @param int $item_id
	 *
	 * @return int
	 */
	public function get_qty( int $item_id ) {
		$qty = $this->get_item_value( 'line_item_qtys', $item_id );
		if ( $qty === null ) {
			$qty = 1;
		}
		return (int) $qty;
	}

	/**
	 * Get item total
	 *
	 * @param integer $item_id
	 *
	 * @return float
	 */
	public function get_total( int $item_id ) {
		return (float) $this->get_item_value( 'line_item_totals', $item_id );
	}

	/**
	 * Get item tax total
	 *
	 * @param integer $item_id
	 *
	 * @return array|null
	 */
	public function get_tax_totals( int $item_id ) {
		return $this->get_item_value( 'line_item_tax_totals', $item_id );
	}

	/**
	 * Get items
	 *
	 * @return Buckaroo_Order_Item[]
	 */
	public function get_items() {
		return $this->items;
	}

	/**
	 * Get qty/totals/tax value for item with item id,
	 *
	 * @param array   $item_list
	 * @param integer $item_id
	 *
	 * @return float|null
	 */
	private function get_item_value( string $item_list, int $item_id ) {
		if ( isset( $this->data[ $item_list ] ) ) {
			$data = json_decode( $this->data[ $item_list ], true );
			return $data[ $item_id ] ?? null;
		}
	}
	/**
	 * Init order items from item ids
	 *
	 * @return void
	 */
	private function init_items() {
		if ( ! isset( $this->data['line_item_totals'] ) ) {
			return;
		}
		$this->item_ids = array_keys( json_decode( $this->data['line_item_totals'], true ) );
		$items          = array_map(
			function ( $itemId ) {
				return $this->get_item( $itemId );
			},
			$this->item_ids
		);

		$this->items = array_filter(
			$items,
			function ( $item ) {
				return $item !== null;
			}
		);
	}
	/**
	 * Get order item by id
	 *
	 * @param integer $item_id
	 *
	 * @return Buckaroo_Order_Item|null
	 */
	protected function get_item( int $item_id ) {
		$item = WC_Order_Factory::get_order_item( $item_id );

		if ( $item === false ) {
			return;
		}
		return new Buckaroo_Order_Item(
			$item,
			$this->order
		);
	}

	/**
	 * Get woo order
	 *
	 * @return WC_Order
	 */
	public function get_order() {
		return $this->order;
	}
}
