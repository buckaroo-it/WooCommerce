<?php

/**
 * Core class for logging
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
class Buckaroo_Order_Details
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
     * Checks if WooCommerce Version 3 or greater is installed
     *
     * @return boolean
     */
    protected function wooV3Plus()
    {
        return substr(WC()->version, 0, 1) >= 3;
    }
    /**
     * Get billing info from order
     *
     * @param string $field
     * @param string $default
     *
     * @return string
     */
    public function getBilling($field, $default = '')
    {
        return $this->get("billing_".$field, $default);
    }
    /**
     * Get shipping info from order or billing info if shipping is empty
     *
     * @param string $field
     * @param string $default
     *
     * @return string
     */
    public function getShipping($field, $default = '')
    {
        $value = $this->get("shipping_".$field);
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
        return $this->getAddressComponents(
            $this->getBilling('address_1'). " " .$this->getBilling('address_2')
        );
    }
    /**
     * Get shipping address components
     *
     * @return array
     */
    public function getShippingAddressComponents()
    {
        return $this->getAddressComponents(
            $this->getShipping('address_1'). " " .$this->getShipping('address_2')
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
     * @param string $field
     * @param string $default
     *
     * @return mixed
     */
    public function get($field, $default = '')
    {
        $value = null;

        if ($this->wooV3Plus()) {
            $method = "get_" . $field;
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
     * @param string $address
     * @return array
     */
    protected function getAddressComponents($address)
    {
        $result                    = array();
        $result['house_number']    = '';
        $result['number_addition'] = '';

        $address = str_replace(array('?', '*', '[', ']', ',', '!'), ' ', $address);
        $address = preg_replace('/\s\s+/', ' ', $address);

        preg_match('/^([0-9]*)(.*?)([0-9]+)(.*)/', $address, $matches);

        if (!empty($matches[2])) {
            $result['street']          = trim($matches[1] . $matches[2]);
            $result['house_number']    = trim($matches[3]);
            $result['number_addition'] = trim($matches[4]);
        } else {
            $result['street'] = $address;
        }

        return $result;
    }
    /**
     * Cleanup a phonenumber handed to it as $phone.
     *
     * @param string $phone phonenumber
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
     * @param string $str
     *
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
    
}