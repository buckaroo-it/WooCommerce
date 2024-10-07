<?php

namespace Buckaroo\Woocommerce\Components;


use Buckaroo\Woocommerce\Services\CaptureTransaction;
use Buckaroo\Woocommerce\Services\HttpRequest;

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
class OrderCapture
{

    /**
     * @var OrderDetails
     */
    protected $order_details;

    /**
     * @var HttpRequest
     */
    protected $request;

    /**
     * @var  OrderItem[]
     */
    protected $form_items;
    /**
     * @var CaptureTransaction[]
     */
    protected $previous_captures = array();
    private $item_qtys;
    private $item_totals;
    private $item_tax_totals;

    public function __construct(OrderDetails $order_details, HttpRequest $request)
    {
        $this->order_details = $order_details;
        $this->request = $request;
        $this->init_form_inputs();
        $this->init_form_items();
        $this->init_previous_captures();
    }

    /**
     * Sanitize inputs and store them in private properties
     *
     * @return void
     */
    private function init_form_inputs()
    {
        $this->item_qtys = $this->sanitize_json('line_item_qtys');
        $this->item_totals = $this->sanitize_json('line_item_totals');
        $this->item_tax_totals = $this->sanitize_json('line_item_tax_totals');
    }

    /**
     * Convert $_POST json string to array and sanitize it
     *
     * @param string $key
     *
     * @return array
     */
    private function sanitize_json($key)
    {
        if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
            return array();
        }

        $result = json_decode(wp_unslash($_POST[$key]), true);
        if (!is_array($result)) {
            return array();
        }

        return map_deep(
            $result,
            'sanitize_text_field'
        );
    }

    /**
     * Init order items from item ids
     *
     * @return void
     */
    private function init_form_items()
    {
        $input_item_ids = array_keys($this->item_totals);
        $form_items = array_map(
            function ($itemId) {
                return $this->order_details->get_item($itemId);
            },
            $input_item_ids
        );

        $this->form_items = array_filter(
            $form_items,
            function ($item) {
                return $item !== null;
            }
        );
    }

    /**
     * Init previous captures
     *
     * @return void
     */
    public function init_previous_captures()
    {
        $previous_captures = $this->order_details->get_meta('_wc_order_captures');

        if (!is_array($previous_captures)) {
            return array();
        }
        $this->previous_captures = array_map(
            function ($capture_transaction) {
                return new CaptureTransaction($capture_transaction, $this->order_details->get_order());
            },
            $previous_captures
        );
    }

    /**
     * Get order details
     *
     * @return OrderDetails
     */
    public function get_order_details()
    {
        return $this->order_details;
    }

    /**
     * Get previous captures
     *
     * @return array
     */
    public function get_previous_captures()
    {
        return $this->previous_captures;
    }

    /**
     * Get items available for capture
     *
     * @return OrderCaptureItem[]
     */
    public function get_available_to_capture()
    {
        $available_items = array();
        $order_items = $this->order_details->get_items_for_capture();
        foreach ($order_items as $order_item) {
            $available_items[] = new OrderCaptureItem(
                $order_item,
                $this->get_previous_capture_with_item($order_item)
            );
        }

        return array_filter(
            $available_items,
            function ($item) {
                return $item->is_available_for_capture();
            }
        );
    }

    /**
     * Get transactions that have item
     *
     * @param OrderItem $item
     *
     * @return CaptureTransaction[]
     */
    protected function get_previous_capture_with_item(OrderItem $item)
    {
        return array_filter(
            $this->previous_captures,
            function ($capture_transaction) use ($item) {
                return $capture_transaction->has_item($item->get_line_item_id());
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
    public function get_item_qty(int $item_id)
    {
        $qty = $this->get_input_item_value($this->item_qtys, $item_id);
        if ($qty === null) {
            $qty = 1;
        }
        return (int)$qty;
    }

    /**
     * Get qty/totals/tax value for item with item id,
     * returns 0 if not found
     *
     * @param array $item_list
     * @param integer $item_id
     *
     * @return float|null
     */
    private function get_input_item_value(array $item_list, int $item_id)
    {
        if (isset($item_list[$item_id])) {
            return $item_list[$item_id];
        }
    }

    /**
     * Get item total from form
     *
     * @param integer $item_id
     *
     * @return float
     */
    public function get_item_total(int $item_id)
    {
        return (float)$this->get_input_item_value($this->item_totals, $item_id);
    }

    /**
     * Get item tax total from form
     *
     * @param integer $item_id
     *
     * @return array|null
     */
    public function get_item_tax_totals(int $item_id)
    {
        return $this->get_input_item_value($this->item_tax_totals, $item_id);
    }

    /**
     * Get form items
     *
     * @return OrderItem[]
     */
    public function get_form_items()
    {
        return $this->form_items;
    }
}