<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Services\Helper;

class KlarnaOrderArticles extends OrderArticles
{
    public function get_products_for_capture(CaptureAllocation $allocation, float $amount): array
    {
        $products = [];

        foreach ($this->order_details->get_items_for_capture() as $item) {
            $itemId = $item->get_line_item_id();
            if (! array_key_exists($itemId, $allocation->getTotals())) {
                continue;
            }

            $quantity = $allocation->getQuantity($itemId);
            $total = $allocation->getTotal($itemId);
            if ($quantity <= 0 || abs($total) < 0.01) {
                continue;
            }

            $product = $this->get_product_data($item);
            $product['quantity'] = $quantity;
            $product['price'] = Helper::roundAmount($total / $quantity);
            $products[] = $product;
        }

        $difference = $this->get_product_with_differences($products, $amount);
        if (is_array($difference)) {
            $products[] = $difference;
        }

        return $products;
    }
}
