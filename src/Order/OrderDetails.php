<?php

namespace Buckaroo\Woocommerce\Order;

use Buckaroo\Woocommerce\Services\Helper;
use WC_Order;
use WC_Order_Factory;

/**
 * Core class for order details
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
     * Get billing info from order
     *
     * @param  string  $field
     * @param  string  $default
     * @return string
     */
    public function getBilling($field, $default = '')
    {
        return $this->get('billing_' . $field, $default);
    }

    /**
     * Get shipping info from order or billing info if shipping is empty
     *
     * @param  string  $field
     * @param  string  $default
     * @return string
     */
    public function getShipping($field, $default = '')
    {
        $value = $this->get('shipping_' . $field);
        if (empty($value)) {
            $value = $this->getBilling($field, $default);
        }

        return $value;
    }

    /**
     * Get billing address components
     *
     * @return array
     */
    public function getBillingAddressComponents()
    {
        return self::getAddressComponents(
            $this->getBilling('address_1') . ' ' . $this->getBilling('address_2')
        );
    }

    /**
     * Get shipping address components
     *
     * @return array
     */
    public function getShippingAddressComponents()
    {
        return self::getAddressComponents(
            $this->getShipping('address_1') . ' ' . $this->getShipping('address_2')
        );
    }

    /**
     * Get billing phone
     *
     * @return string
     */
    public function getBillingPhone()
    {
        return $this->cleanupPhone(
            $this->getBilling('phone')
        );
    }

    /**
     * Get shipping phone
     *
     * @return string
     */
    public function getShippingPhone()
    {
        return $this->cleanupPhone(
            $this->getShipping('phone')
        );
    }

    /**
     * Get info from order
     *
     * @param  string  $field
     * @param  string  $default
     * @return mixed
     */
    public function get($field, $default = '')
    {
        $value = null;

        if (Helper::isWooCommerceVersion3OrGreater()) {
            $method = 'get_' . $field;
            if (method_exists($this->order, $method)) {
                $value = $this->order->{$method}();
            }
        } else {
            $value = $this->order->{$field};
        }

        if (empty($value)) {
            $value = $default;
        }

        return $value;
    }

    /**
     * Split address to parts
     *
     * @param  string  $address
     * @return array
     */
    public static function getAddressComponents($address)
    {
        $result = [];
        $result['house_number'] = '';
        $result['number_addition'] = '';

        $address = str_replace(['?', '*', '[', ']', ',', '!'], ' ', $address);
        $address = preg_replace('/\s\s+/', ' ', $address);

        preg_match('/^([0-9]*)(.*?)([0-9]+)(.*)/', $address, $matches);

        if (! empty($matches[2])) {
            $result['street'] = trim($matches[1] . $matches[2]);
            $result['house_number'] = trim($matches[3]);
            $result['number_addition'] = trim($matches[4]);
        } else {
            $result['street'] = $address;
        }

        return $result;
    }

    /**
     * Cleanup a phonenumber handed to it as $phone.
     *
     * @param  string  $phone  phonenumber
     * @return array
     */
    protected function cleanupPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Cleaning up dutch mobile numbers being entered incorrectly
        if (substr($phone, 0, 3) == '316' || substr($phone, 0, 5) == '00316' || substr($phone, 0, 6) == '003106' || substr($phone, 0, 2) == '06') {
            if (substr($phone, 0, 6) == '003106') {
                $phone = substr_replace($phone, '00316', 0, 6);
            }
        }

        return $phone;
    }

    /**
     * Get intials
     *
     * @param  string  $str
     * @return void
     */
    public function getInitials($str)
    {
        $ret = '';
        foreach (explode(' ', $str) as $word) {
            $ret .= strtoupper($word[0]) . '.';
        }

        return $ret;
    }

    /**
     * Get articles
     *
     * @return OrderItem[]
     */
    public function get_products()
    {
        return $this->formatOrderItems(
            $this->order->get_items('line_item')
        );
    }

    /**
     * Get shipment
     *
     * @return OrderItem[]
     */
    public function get_shipping_items()
    {
        return $this->formatOrderItems(
            $this->order->get_items('shipping')
        );
    }

    /**
     * Get fees
     *
     * @return OrderItem[]
     */
    public function get_fees()
    {
        return $this->formatOrderItems(
            $this->order->get_items('fee')
        );
    }

    /**
     * Get items that can be captured
     *
     * @return OrderItem[]
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
     * Format woocommerce order items
     *
     *
     * @return OrderItem[]
     */
    private function formatOrderItems(array $items)
    {
        return array_map(
            function ($item) {
                return new OrderItem($item, $this->order);
            },
            $items
        );
    }

    /**
     * Get order item by id
     *
     *
     * @return OrderItem|null
     */
    public function get_item(int $item_id)
    {
        $item = WC_Order_Factory::get_order_item($item_id);

        if ($item === false) {
            return;
        }

        return new OrderItem(
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
     * Get total formated to 2 decimal
     */
    public function get_total(): float
    {
        return Helper::roundAmount($this->order->get_total('edit'));
    }

    /**
     * Get billing address components
     */
    public function get_billing_address_components(): AddressComponents
    {
        return new AddressComponents(
            $this->get_billing('address_1') . ' ' . $this->get_billing('address_2')
        );
    }

    /**
     * Get billing info from order
     *
     * @param  string  $field
     * @param  string  $default
     * @return string
     */
    public function get_billing($field, $default = '')
    {
        return $this->get('billing_' . $field, $default);
    }

    /**
     * Get shipping address components
     */
    public function get_shipping_address_components(): AddressComponents
    {
        return new AddressComponents(
            $this->get_shipping('address_1') . ' ' . $this->get_shipping('address_2')
        );
    }

    /**
     * Get shipping info from order or billing info if shipping is empty
     *
     * @param  string  $field
     * @param  string  $default
     * @return string
     */
    public function get_shipping($field, $default = '')
    {
        $value = $this->get('shipping_' . $field);
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
     * @param  string  $phone  phonenumber
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

        if (! is_string($phone)) {
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
     * @param  string  $str
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
     * Get full name for address type billing|shipping
     *
     *
     * @return string
     */
    public function get_full_name(string $address_type = 'billing')
    {
        if (! in_array($address_type, ['billing', 'shipping'])) {
            $address_type = 'billing';
        }

        return $this->get($address_type . '_first_name') . ' ' . $this->get($address_type . '_last_name');
    }
}
