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

        $total_order_amount = $this->order_details->get_total('edit');

        if ($this->absorb_difference_into_fee($products, $total_order_amount)) {
            return $products;
        }

        $productDiff = $this->get_product_with_differences($products, $total_order_amount);

        if (is_array($productDiff)) {
            $products[] = $productDiff;
        }

        return $products;
    }

    /**
     * When a Buckaroo fee article is present, absorb any difference between
     * the order total and the summed article prices into the fee line itself
     * instead of emitting a separate `rounding_errors` article.
     *
     * This keeps the article list clean (no extra rounding line) and makes the
     * fee reflect the amount actually charged, e.g. a fee whose gross unit
     * price carries VAT that the order total does not account for.
     *
     * Only applies when the fee has a single unit, so the per-unit gross price
     * stays exact; otherwise the caller falls back to the rounding article.
     *
     * @param  array  $products  Passed by reference so the fee line can be adjusted in place.
     * @return bool True when the difference was absorbed (or there was none).
     */
    protected function absorb_difference_into_fee(array &$products, float $total_order_amount): bool
    {
        $feeIndex = null;
        foreach ($products as $index => $product) {
            if (isset($product['identifier']) && $product['identifier'] === 'BuckarooFee') {
                $feeIndex = $index;
                break;
            }
        }

        if ($feeIndex === null) {
            return false;
        }

        if (! isset($products[$feeIndex]['quantity']) || (int) $products[$feeIndex]['quantity'] !== 1) {
            return false;
        }

        $diffAmount = Helper::roundAmount($total_order_amount - $this->sum_products_amount($products));

        if (abs($diffAmount) < 0.01) {
            return true;
        }

        $products[$feeIndex]['price'] = Helper::roundAmount($products[$feeIndex]['price'] + $diffAmount);

        return true;
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

        if ($this->is_buckaroo_payment_fee_item($item)) {
            $product['identifier'] = 'BuckarooFee';
            $product['description'] = 'Buckaroo Fee';
            $product['vatPercentage'] = $this->resolve_buckaroo_fee_vat_percentage($item);
        }

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
     * Detect the Buckaroo-managed Payment fee order item.
     *
     * Both `OrderActions::add_fee_to_cart` and
     * `AbstractPaymentProcessor::ensureBuckarooFeeItem` create the fee with
     * the same localized name, so we match against that.
     */
    private function is_buckaroo_payment_fee_item(OrderItem $item): bool
    {
        if ($item->get_type() !== 'fee') {
            return false;
        }

        return $item->get_title() === __('Payment fee', 'wc-buckaroo-bpe-gateway');
    }

    /**
     * Resolve the value Buckaroo expects in `VatPercentage` for the fee
     * article. Mirrors the Shopware 6 plugin
     * (FormatRequestParamService::resolveBuckarooFeeVatPercentage):
     *
     * - When the fee is configured as a percentage (`extrachargeamount`
     *   contains `%`), send that configured percentage directly.
     * - Otherwise (fixed fee), derive it from the fee relative to the rest
     *   of the order: `(fee / (orderTotal - fee)) * 100`, falling back to
     *   the full total when the remainder is non-positive.
     *
     * Returns 0.0 when no usable value can be derived.
     */
    private function resolve_buckaroo_fee_vat_percentage(OrderItem $item): float
    {
        $rawAmount = $this->gateway->get_option('extrachargeamount', '');
        $rawAmount = is_scalar($rawAmount) ? trim((string) $rawAmount) : '';

        if (strpos($rawAmount, '%') !== false) {
            $percentageValue = (float) str_replace(['%', ',', ' '], ['', '.', ''], $rawAmount);
            if ($percentageValue >= 0) {
                return Helper::roundAmount($percentageValue);
            }
        }

        $feeAmount = (float) $item->get_unit_price() * (int) $item->get_quantity();
        $totalAmount = (float) $this->order_details->get_order()->get_total('edit');

        $baseAmount = $totalAmount - $feeAmount;
        if ($baseAmount <= 0.0) {
            $baseAmount = $totalAmount;
        }

        if ($baseAmount <= 0.0 || $feeAmount <= 0.0) {
            return 0.0;
        }

        return Helper::roundAmount(($feeAmount / $baseAmount) * 100);
    }

    /**
     * Get custom afterpay attributes
     */
    private function get_afterpay_data(OrderItem $item): array
    {
        $data = [];
        if ($item->get_type() === 'line_item') {
            if ($this->gateway->id === 'buckaroo_afterpaynew') {
                $img = $this->get_product_image($item->get_order_item()->get_product());
                if (! empty($img)) {
                    $data['imageUrl'] = $img;
                }
            }
            $data['url'] = get_permalink($item->get_id());
        }

        return $data;
    }

    public function get_product_image($product)
    {
        $src = get_the_post_thumbnail_url($product->get_id());
        if (! $src) {
            $imgTag = $product->get_image();
            $doc = new DOMDocument();
            $doc->loadHTML($imgTag);
            $xpath = new DOMXPath($doc);
            $src = $xpath->evaluate('string(//img/@src)');
        }

        if (! is_string($src) || $src === '') {
            return;
        }

        if (strpos($src, '?') !== false) {
            $src = substr($src, 0, strpos($src, '?'));
        }

        $srcInfo = $this->safe_remote_getimagesize($src);
        if (! is_array($srcInfo)) {
            return;
        }

        if (! empty($srcInfo['mime']) && in_array($srcInfo['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'])) {
            if (! empty($srcInfo[0]) && ($srcInfo[0] >= 100) && ($srcInfo[0] <= 1280)) {
                return $src;
            }
        }
    }

    /**
     * Wrap getimagesize() so a slow/unreachable remote image cannot block the
     * checkout request. PHP's default_socket_timeout (often 60s) would otherwise
     * be applied per call, multiplied by the number of items in the cart, which
     * is what used to make Riverty/Afterpay checkout hang indefinitely.
     *
     * @param  string  $src
     * @return array|false
     */
    protected function safe_remote_getimagesize(string $src)
    {
        $isRemote = (bool) preg_match('#^https?://#i', $src);

        if (! $isRemote) {
            return @getimagesize($src);
        }

        $previousSocketTimeout = ini_set('default_socket_timeout', '3');
        $previousDefaultOptions = stream_context_get_options(stream_context_get_default());

        stream_context_set_default([
            'http' => [
                'timeout' => 3,
                'follow_location' => 1,
                'ignore_errors' => true,
            ],
            'https' => [
                'timeout' => 3,
                'follow_location' => 1,
                'ignore_errors' => true,
            ],
        ]);

        try {
            return @getimagesize($src);
        } catch (\Throwable $e) {
            return false;
        } finally {
            if ($previousSocketTimeout !== false) {
                ini_set('default_socket_timeout', $previousSocketTimeout);
            }
            stream_context_set_default(is_array($previousDefaultOptions) ? $previousDefaultOptions : []);
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

        $diffAmount = Helper::roundAmount($total_order_amount - $product_amount);

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
