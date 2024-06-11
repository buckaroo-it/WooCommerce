<?php

/**
 * Core class for order items
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
class Buckaroo_Item_For_Capture {

	/**
	 * Order item
	 *
	 * @var Buckaroo_Order_Item
	 */
	protected $order_item;

	/**
	 * Woo order
	 *
	 * @var Buckaroo_Capture_Transaction[]
	 */
	protected $capture_transactions;

	protected $qty;

	public function __construct( Buckaroo_Order_Item $order_item, array $capture_transactions ) {
		$this->order_item           = $order_item;
		$this->capture_transactions = $capture_transactions;
		$this->init();
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->order_item->get_title();
	}

	/**
	 * Get product/fee/shipping id
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->order_item->get_id();
	}

	/**
	 * Get line item id
	 *
	 * @return int
	 */
	public function get_line_item_id() {
		return $this->order_item->get_line_item_id();
	}


	/**
	 * Get quantity remaining to be captured
	 *
	 * @return int
	 */
	public function get_quantity() {
		return $this->qty;
	}

	public function get_total_amount( $inc_tax = true ) {
		return $this->qty * $this->get_unit_price( $inc_tax );
	}
	/**
	 * Can capture item
	 *
	 * @return boolean
	 */
	public function is_available_for_capture() {
		return $this->get_quantity() > 0;
	}

	/**
	 * Get unit price
	 *
	 * @return float
	 */
	public function get_unit_price( $inc_tax = true ) {
		return $this->order_item->get_unit_price( $inc_tax );
	}

	/**
	 * Get vat
	 *
	 * @return float
	 */
	public function get_vat() {
		return $this->order_item->get_vat();
	}

	/**
	 * Get list of taxes
	 *
	 * @return array
	 */
	public function get_taxes() {
		return $this->order_item->get_taxes();
	}
	/**
	 * Get item type
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->order_item->get_type();
	}

	protected function init() {
		$captured_qty = array_reduce(
			$this->capture_transactions,
			function ( $carry, $capture_transaction ) {
				return $carry + $capture_transaction->get_qty(
					$this->get_line_item_id()
				);
			},
			0
		);

		$this->qty = $this->order_item->get_quantity() - $captured_qty;
	}
}
