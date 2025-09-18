<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;

class AbstractAfterpayRefundProcessor extends AbstractRefundProcessor
{
    protected function getMethodBody(): array
    {
        return [
            'articles' => $this->getRefundArticles()
        ];
    }

    protected function getRefundArticles(): array
    {
        $refunded_line_items = $this->getRefundedLineItems();

        if (empty($refunded_line_items)) {
            return $this->getAllArticlesWithRefundType();
        }

        return $this->getPartialRefundArticles($refunded_line_items);
    }

    protected function getAllArticlesWithRefundType(): array
    {
        $order = $this->getOrder();
        $order_details = new OrderDetails($order);
        $order_articles = new OrderArticles($order_details, $this->gateway);
        $articles = $order_articles->get_products_for_payment();

        return array_map(fn($article) => $article + ['refundType' => 'Return'], $articles);
    }

    protected function getPartialRefundArticles(array $refunded_line_items): array
    {
        $order = $this->getOrder();
        $order_details = new OrderDetails($order);
        $refund_articles = [];

        $refunded_items = [];
        foreach ($refunded_line_items as $line_item) {
            $refunded_items[$line_item['item_id']] = (int) $line_item['qty'];
        }

        foreach ($order_details->get_products() as $order_item) {
            $line_item_id = $order_item->get_line_item_id();

            if (isset($refunded_items[$line_item_id]) && $refunded_items[$line_item_id] > 0) {
                $refund_articles[] = $this->buildAfterpayArticle(
                    $order_item,
                    $refunded_items[$line_item_id]
                );
            }
        }

        return $refund_articles;
    }

    protected function buildAfterpayArticle($order_item, int $quantity): array
    {
        $article = [
            'identifier' => $order_item->get_id(),
            'description' => $order_item->get_title(),
            'price' => round($order_item->get_unit_price(), 2),
            'quantity' => $quantity,
            'refundType' => 'Return',
        ];

        return array_merge($article, $this->getVatData($order_item->get_vat()));
    }

    protected function getVatData(float $vatPercentage): array
    {
        return [];
    }
}
