<?php

namespace Buckaroo\Woocommerce\Order;

use Buckaroo\Woocommerce\Services\Helper;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Tax;

/**
 * Core class for order items
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 2.25.0
 *
 * @link      https://www.buckaroo.eu/
 */
class OrderItem
{
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

    public function __construct(WC_Order_Item $orderItem, WC_Order $order)
    {
        $this->order_item = $orderItem;
        $this->order = $order;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function get_title()
    {
        $title = $this->order_item->get_name();
        if ($title !== null) {
            return $title;
        }

        return $this->get_id();
    }

    /**
     * Get product/fee/shipping id
     *
     * @return int
     */
    public function get_id()
    {
        if ($this->order_item instanceof WC_Order_Item_Product) {
            if ($this->order_item->get_variation_id() !== $this->order_item->get_product_id() && $this->order_item->get_variation_id() > 0) {
                return $this->order_item->get_variation_id();
            }

            return $this->order_item->get_product_id();
        }
        if ($this->order_item instanceof WC_Order_Item_Fee) {
            return $this->order_item->get_name();
        }
        if ($this->order_item instanceof WC_Order_Item_Shipping) {
            return $this->order_item->get_method_id();
        }

        return empty($this->order_item->get_name()) ?
            bin2hex(random_bytes(16)) :
            sanitize_title($this->order_item->get_name());
    }

    /**
     * Get line item id
     *
     * @return int
     */
    public function get_line_item_id()
    {
        return $this->order_item->get_id();
    }

    /**
     * Get quantity
     *
     * @return int
     */
    public function get_quantity()
    {
        return $this->order_item->get_quantity();
    }

    /**
     * Get unit price
     *
     * @return float
     */
    public function get_unit_price($inc_tax = true)
    {
        return $this->order->get_item_total($this->order_item, $inc_tax);
    }

    /**
     * Get vat
     *
     * @return float
     */
    public function get_vat()
    {
        if ($this->order_item instanceof WC_Order_Item_Shipping) {
            $shipping_tax_class = get_option('woocommerce_shipping_tax_class');
            if ($shipping_tax_class === 'inherit' || $shipping_tax_class === '') {
                $tax_classes = [];
                foreach ($this->order->get_items('line_item') as $line_item) {
                    $product = $line_item->get_product();
                    if ($product) {
                        $item_tax_class = $product->get_tax_class();
                        $item_tax_class = $item_tax_class ? $item_tax_class : '';
                        if (!in_array($item_tax_class, $tax_classes, true)) {
                            $tax_classes[] = $item_tax_class;
                        }
                    }
                }
                if (!empty($tax_classes)) {
                    $tax_class_to_use = $tax_classes[0];
                    $tax = new WC_Tax();
                    $taxes = $tax->get_rates($tax_class_to_use);
                    if (count($taxes)) {
                        $taxRate = array_shift($taxes);
                        if (isset($taxRate['rate'])) {
                            return Helper::roundAmount($taxRate['rate']);
                        }
                    }
                }
            }
        }
        $tax = new WC_Tax();
        $taxes = $tax->get_rates($this->order_item->get_tax_class());
        if (! count($taxes)) {
            return 0;
        }
        $taxRate = array_shift($taxes);
        if (! isset($taxRate['rate'])) {
            return 0;
        }

        return Helper::roundAmount($taxRate['rate']);
    }

    /**
     * Get order item
     *
     * @return WC_Order_Items
     */
    public function get_order_item()
    {
        return $this->order_item;
    }

    /**
     * Get list of taxes
     *
     * @return array
     */
    public function get_taxes()
    {
        if (method_exists($this->order_item, 'get_taxes')) {
            return $this->order_item->get_taxes();
        }

        return [];
    }

    /**
     * Get type
     *
     * @return string
     */
    public function get_type()
    {
        return $this->order_item->get_type();
    }

    /**
     * Get type
     *
     * @return string
     */
    public function get_currency()
    {
        return $this->order_item->get_currency();
    }
}
