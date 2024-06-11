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
class Buckaroo_Order_Item {

	/**
	 * Woocommerce order item
	 *
	 * @var WC_Order_Item
	 */
	protected $order_item;

	/**
	 * Woo order
	 *
	 * @var WC_Order
	 */
	protected $order;

	public function __construct( WC_Order_Item $orderItem, WC_Order $order ) {
		$this->order_item = $orderItem;
		$this->order      = $order;
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function get_title() {
		$title = $this->order_item->get_name();
		if ( $title !== null ) {
			return $title;
		}
		return $this->get_id();
	}

	/**
	 * Get product/fee/shipping id
	 *
	 * @return int
	 */
	public function get_id() {
		if ( $this->order_item instanceof WC_Order_Item_Product ) {

			if ( $this->order_item->get_variation_id() !== $this->order_item->get_product_id() && $this->order_item->get_variation_id() > 0 ) {
				return $this->order_item->get_variation_id();
			}
			return $this->order_item->get_product_id();
		}
		if ( $this->order_item instanceof WC_Order_Item_Fee ) {
			return $this->order_item->get_name();
		}
		if ( $this->order_item instanceof WC_Order_Item_Shipping ) {
			return $this->order_item->get_method_id();
		}
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Get line item id
	 *
	 * @return int
	 */
	public function get_line_item_id() {
		return $this->order_item->get_id();
	}


	/**
	 * Get quantity
	 *
	 * @return int
	 */
	public function get_quantity() {
		return $this->order_item->get_quantity();
	}

	/**
	 * Get unit price
	 *
	 * @return float
	 */
	public function get_unit_price( $inc_tax = true ) {

		return $this->order->get_item_total( $this->order_item, $inc_tax );
	}

	/**
	 * Get vat
	 *
	 * @return float
	 */
	public function get_vat() {
		$tax   = new WC_Tax();
		$taxes = $tax->get_rates( $this->order_item->get_tax_class() );
		if ( ! count( $taxes ) ) {
			return 0;
		}
		$taxRate = array_shift( $taxes );
		if ( ! isset( $taxRate['rate'] ) ) {
			return 0;
		}

		return number_format( $taxRate['rate'], 2 );
	}

	/**
	 * Get order item
	 *
	 * @return \WC_Order_Items
	 */
	public function get_order_item() {
		return $this->order_item;
	}

	/**
	 * Get list of taxes
	 *
	 * @return array
	 */
	public function get_taxes() {
		if ( method_exists( $this->order_item, 'get_taxes' ) ) {
			return $this->order_item->get_taxes();
		}
		return array();
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->order_item->get_type();
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->order_item->get_currency();
	}
}
