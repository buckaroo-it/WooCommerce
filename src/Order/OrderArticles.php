<?php

namespace Buckaroo\Woocommerce\Order;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Services\Helper;
use DOMDocument;
use DOMXPath;

/**
 * Core class for logging
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
class OrderArticles
{
    /**
     * @var OrderDetails
     */
    protected $order_details;

    protected AbstractPaymentGateway $gateway;

    public function __construct(
        OrderDetails $order_details,
        AbstractPaymentGateway $gateway
    ) {
        $this->order_details = $order_details;
        $this->gateway = $gateway;
    }

    /**
     * Get all the products from order for a payment
     *
     * @param  OrderDetails  $order_details
     */
    public function get_products_for_payment(): array
    {
        $products = array_map(
            function (OrderItem $item) {
                return $this->get_product_data($item);
            },
            array_merge(
                $this->order_details->get_products(),
                $this->order_details->get_shipping_items(),
                $this->order_details->get_fees()
            )
        );

        $productDiff = $this->get_product_with_differences($products, $this->order_details->get_total('edit'));

        if (is_array($productDiff)) {
            $products[] = $productDiff;
        }

        return $products;
    }

    /**
     * Get formated product data
     */
    public function get_product_data(OrderItem $item): array
    {
        $product = [
            'identifier' => $item->get_id(),
            'description' => $item->get_title(),
            'price' => round($item->get_unit_price(), 2),
            'quantity' => $item->get_quantity(),
            'vatPercentage' => $item->get_vat(),
        ];

        if ($this->gateway->id === 'buckaroo_afterpaynew' || $this->gateway->id === 'buckaroo_klarnapay') {
            $product = array_merge($product, $this->get_afterpay_data($item));
        }

        if ($this->gateway->id === 'buckaroo_afterpay') {
            $vat_category = $this->gateway->get_option('vattype');
            if (! is_scalar($vat_category) || $item->get_type() !== 'line_item') {
                $vat_category = 4;
            }
            unset($product['vatPercentage']);
            $product['vatCategory'] = intval($vat_category);
        }

        return $product;
    }

    /**
     * Get custom afterpay attributes
     */
    private function get_afterpay_data(OrderItem $item): array
    {
        $data = [];
        if ($item->get_type() === 'line_item') {
            $img = $this->get_product_image($item->get_order_item()->get_product());
            if (! empty($img)) {
                $data['imgUrl'] = $img;
            }
            $data['url'] = get_permalink($item->get_id());
        }

        return $data;
    }

    public function get_product_image($product)
    {
        if ($this->gateway->get_option('sendimageinfo')) {
            $src = get_the_post_thumbnail_url($product->get_id());
            if (! $src) {
                $imgTag = $product->get_image();
                $doc = new DOMDocument();
                $doc->loadHTML($imgTag);
                $xpath = new DOMXPath($doc);
                $src = $xpath->evaluate('string(//img/@src)');
            }

            if (strpos($src, '?') !== false) {
                $src = substr($src, 0, strpos($src, '?'));
            }

            if ($srcInfo = @getimagesize($src)) {
                if (! empty($srcInfo['mime']) && in_array($srcInfo['mime'], ['image/png', 'image/jpeg'])) {
                    if (! empty($srcInfo[0]) && ($srcInfo[0] >= 100) && ($srcInfo[0] <= 1280)) {
                        return $src;
                    }
                }
            }
        }
    }

    /**
     * Get any rounding errors between the final amount and the sum of the products
     *
     *
     * @return array|null
     */
    protected function get_product_with_differences(array $products, float $total_order_amount)
    {
        $product_amount = $this->sum_products_amount($products);

        $diffAmount = $total_order_amount - $product_amount;

        if (abs($diffAmount) >= 0.01) {
            $product = [
                'identifier' => 'rounding_errors',
                'description' => 'Rounding errors',
                'price' => $diffAmount,
                'quantity' => 1,
                'vatPercentage' => 0,
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
     *
     * @return float
     */
    protected function sum_products_amount(array $products)
    {
        return Helper::roundAmount(
            array_reduce(
                $products,
                function ($carier, $product) {
                    if (isset($product['price']) && isset($product['quantity'])) {
                        return $carier + ($product['price'] * $product['quantity']);
                    }

                    return $carier;
                },
                0
            )
        );
    }
}
