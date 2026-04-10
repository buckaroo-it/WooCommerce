<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;
use WC_Order_Refund;

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

        if (!empty($refunded_line_items)) {
            return $this->getPartialRefundArticles($refunded_line_items);
        }

        return $this->buildPartialAmountArticles() ?? $this->getAllArticlesWithRefundType();
    }

    protected function buildPartialAmountArticles(): ?array
    {
        $refund_amount = $this->resolvePartialRefundAmount();
        if ($refund_amount === null) {
            return null;
        }

        $first_product = $this->resolveFallbackProduct();
        if ($first_product === null) {
            return null;
        }

        $article = $this->buildAfterpayArticle($first_product, 1);
        $article['price'] = $refund_amount;

        return [$article];
    }

    protected function resolvePartialRefundAmount(): ?float
    {
        $order = $this->getOrder();
        $refunds = $order->get_refunds();
        if (empty($refunds)) {
            return null;
        }

        $latest = reset($refunds);
        if (!$latest instanceof WC_Order_Refund) {
            return null;
        }

        $refund_amount = round(abs((float) $latest->get_amount()), 2);
        $order_total = round((float) $order->get_total(), 2);

        if (abs($order_total - $refund_amount) < 0.01) {
            return null;
        }

        return $refund_amount;
    }

    protected function resolveFallbackProduct()
    {
        $products = (new OrderDetails($this->getOrder()))->get_products();

        return empty($products) ? null : reset($products);
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
