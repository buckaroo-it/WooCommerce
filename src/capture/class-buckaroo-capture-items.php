<?php

namespace WC_Buckaroo\WooCommerce\Capture;

use Buckaroo_Http_Request;
use Buckaroo_Order_Item;
use WC_Buckaroo\WooCommerce\Payment\Buckaroo_Order_Details;
use WC_Gateway_Buckaroo;

class Buckaroo_Capture_Items
{
    private Buckaroo_Order_Details $order_details;

    private Buckaroo_Http_Request $request;

    private WC_Gateway_Buckaroo $gateway;

    public function __construct(
        Buckaroo_Order_Details $order_details,
        Buckaroo_Http_Request $request,
        WC_Gateway_Buckaroo   $gateway
    )
    {
        $this->order_details = $order_details;
        $this->request = $request;
        $this->gateway = $gateway;
    }

    public function get_order_details(): Buckaroo_Order_Details
    {
        return $this->order_details;
    }


    /**
     * Get all the products from order for a capture
     *
     * @param float $total
     *
     * @return array
     */
    public function get_products(float $total): array
    {

        $products = array_map(
            function (Buckaroo_Order_Item $item) {
                return $this->get_product_data($item);
            },
            array_merge(
                $this->order_details->get_products(),
                $this->order_details->get_shipping_items(),
                $this->order_details->get_fees()
            )
        );

        $productDiff = $this->get_product_with_differences($products, $total);

        if (is_array($productDiff)) {
            $products[] = $productDiff;
        }
        return array_filter(
            $products,
            function ($product) {
                return $product['quantity'] > 0;
            }
        );
    }

    /**
     * Get formated product data
     *
     * @param Buckaroo_Order_Item $item
     *
     * @return array
     */
    public function get_product_data(Buckaroo_Order_Item $item): array
    {
        $quantity = $item->get_quantity($item);
        $product = [
            'identifier' => $item->get_id(),
            'description' => $item->get_title(),
            'price' => round($item->get_unit_price(), 2),
            'quantity' => $quantity,
            'vatPercentage' => $item->get_vat()
        ];

        if ($this->gateway->id === 'buckaroo_afterpay') {
            $vat_category = $this->gateway->get_option('vattype');
            if (!is_scalar($vat_category) || $item->get_type() !== 'line_item') {
                $vat_category = 4;
            }
            unset($product['vatPercentage']);
            $product['vatCategory'] = intval($vat_category);
        }


        return $product;
    }


    protected function get_quantity($item): int
    {
        $quantities = $this->request->request('line_item_qtys');
        $quantity = $quantities[$item->get_id()] ?? 0;
        if (!is_scalar($quantity)) {
            return 0;
        }

        return (int)$quantity;
    }

    /**
     * Get any rounding errors between the final amount and the sum of the products
     *
     * @param array $products
     * @param float $total_order_amount
     *
     * @return array|null
     */
    protected function get_product_with_differences(array $products, float $total_order_amount)
    {
        $product_amount = $this->sum_products_amount($products);

        $diffAmount = round((float)number_format($total_order_amount, 2) - $product_amount, 2);

        if (abs($diffAmount) >= 0.01) {
            $product = [
                'identifier' => 'capture_adjustment',
                'description' => 'Capture amount adjustment',
                'price' => $diffAmount,
                'quantity' => 1,
                'vatPercentage' => 0
            ];

            if ($this->gateway->id === 'buckaroo_afterpay') {
                unset($product['vatPercentage']);
                $product['vatCategory'] = 4;
            }

            return $product;
        }
    }

    /**
     * Sum all products amounts
     *
     * @param array $products
     *
     * @return float
     */
    protected function sum_products_amount(array $products)
    {
        return array_reduce(
            $products,
            function ($carier, $product) {
                if (isset($product['price']) && isset($product['quantity'])) {
                    return $carier + ($product['price'] * $product['quantity']);
                }
                return $carier;
            },
            0
        );
    }
}
