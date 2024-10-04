<?php

namespace Buckaroo\Woocommerce\Components;

use Buckaroo_Order_Item;
use WC_Order;
use WC_Order_Factory;

/**
 * Core class for order details
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
class OrderDetails
{
    /**
     * Woocommerce order
     *
     * @var WC_Order
     */
    protected $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * Get woocommerce order
     *
     * @return WC_Order
     */
    public function get_order()
    {
        return $this->order;
    }

    /**
     * Get total formated to 2 decimal
     *
     * @return float
     */
    public function get_total(): float
    {
        return number_format(floatval($this->order->get_total('edit')), 2);
    }

    /**
     * Get billing address components
     *
     * @return AddressComponents
     */
    public function get_billing_address_components(): AddressComponents
    {
        return new AddressComponents(
            $this->get_billing('address_1') . " " . $this->get_billing('address_2')
        );
    }

    /**
     * Get billing info from order
     *
     * @param string $field
     * @param string $default
     *
     * @return string
     */
    public function get_billing($field, $default = '')
    {
        return $this->get("billing_" . $field, $default);
    }

    /**
     * Get info from order
     *
     * @param string $field
     * @param string $default
     *
     * @return mixed
     */
    public function get($field, $default = '')
    {
        $value = '';
        $method = "get_" . $field;
        if (method_exists($this->order, $method)) {
            $value = $this->order->{$method}();
        }

        if (empty($value)) {
            $value = $default;
        }

        return $value;
    }

    /**
     * Get shipping address components
     *
     * @return AddressComponents
     */
    public function get_shipping_address_components(): AddressComponents
    {
        return new AddressComponents(
            $this->get_shipping('address_1') . " " . $this->get_shipping('address_2')
        );
    }

    /**
     * Get shipping info from order or billing info if shipping is empty
     *
     * @param string $field
     * @param string $default
     *
     * @return string
     */
    public function get_shipping($field, $default = '')
    {
        $value = $this->get("shipping_" . $field);
        if (empty($value)) {
            $value = $this->get_billing($field, $default);
        }
        return $value;
    }

    /**
     * Get billing phone
     *
     * @return string
     */
    public function get_billing_phone()
    {
        return $this->cleanup_phone(
            $this->get_billing('phone')
        );
    }

    /**
     * Cleanup a phonenumber handed to it as $phone.
     *
     * @param string $phone phonenumber
     * @return string
     */
    public function cleanup_phone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Cleaning up dutch mobile numbers being entered incorrectly
        if (substr($phone, 0, 3) == '316' || substr($phone, 0, 5) == '00316' || substr($phone, 0, 6) == '003106' || substr($phone, 0, 2) == '06') {
            if (substr($phone, 0, 6) == '003106') {
                $phone = substr_replace($phone, '00316', 0, 6);
            }
        }

        if (!is_string($phone)) {
            return '';
        }
        return $phone;
    }

    /**
     * Get shipping phone
     *
     * @return string
     */
    public function get_shipping_phone()
    {
        return $this->cleanup_phone(
            $this->get_shipping('phone')
        );
    }

    /**
     * Get intials
     *
     * @param string $str
     *
     * @return void
     */
    public function get_initials($str)
    {
        $ret = '';
        foreach (explode(' ', $str) as $word) {
            $ret .= strtoupper($word[0]) . '.';
        }
        return $ret;
    }

    /**
     * Get items that can be captured
     *
     * @return Buckaroo_Order_Item[]
     */
    public function get_items_for_capture()
    {
        return array_merge(
            $this->get_products(),
            $this->get_shipping_items(),
            $this->get_fees()
        );
    }

    /**
     * Get articles
     *
     * @return Buckaroo_Order_Item[]
     */
    public function get_products()
    {
        return $this->format_order_items(
            $this->order->get_items('line_item')
        );
    }

    /**
     * Format woocommerce order items
     *
     * @param array $items
     *
     * @return Buckaroo_Order_Item[]
     */
    private function format_order_items(array $items)
    {
        return array_map(
            function ($item) {
                return new Buckaroo_Order_Item($item, $this->order);
            },
            $items
        );
    }

    /**
     * Get shipment
     *
     * @return Buckaroo_Order_Item[]
     */
    public function get_shipping_items()
    {
        return $this->format_order_items(
            $this->order->get_items('shipping')
        );
    }

    /**
     * Get fees
     *
     * @return Buckaroo_Order_Item[]
     */
    public function get_fees()
    {
        return $this->format_order_items(
            $this->order->get_items('fee')
        );
    }

    /**
     * Get order item by id
     *
     * @param integer $item_id
     *
     * @return Buckaroo_Order_Item|null
     */
    public function get_item(int $item_id)
    {
        $item = WC_Order_Factory::get_order_item($item_id);

        if ($item === false) {
            return;
        }
        return new Buckaroo_Order_Item(
            $item,
            $this->order
        );
    }

    public function update_meta(string $key, $value)
    {
        return update_post_meta($this->order->get_id(), $key, $value);
    }

    public function add_meta(string $key, $value, $unique = false)
    {
        return add_post_meta($this->order->get_id(), $key, $value, $unique);
    }

    public function get_meta(string $key, $single = false)
    {
        return get_post_meta($this->order->get_id(), $key, $single);
    }

    /**
     * Get order currency
     *
     * @return string
     */
    public function get_currency()
    {
        return $this->order->get_currency();
    }


    /**
     * Get full name for address type billing|shipping
     *
     * @param string $address_type
     *
     * @return string
     */
    public function get_full_name(string $address_type = 'billing')
    {
        if (!in_array($address_type, ["billing", "shipping"])) {
            $address_type = "billing";
        }
        return $this->get($address_type . '_first_name') . " " . $this->get($address_type . '_last_name');
    }
}
