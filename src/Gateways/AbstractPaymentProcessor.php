<?php

namespace Buckaroo\Woocommerce\Gateways;

use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\Request;
use WC_Order;
use WC_Order_Item_Fee;

class AbstractPaymentProcessor extends AbstractProcessor
{
    protected OrderDetails $order_details;

    protected OrderArticles $order_articles;

    protected Request $request;

    public function __construct(
        AbstractPaymentGateway $gateway,
        OrderDetails $order_details,
        OrderArticles $order_articles
    ) {
        $this->request = new Request();
        $this->gateway = $gateway;
        $this->order_details = $order_details;
        $this->order_articles = $order_articles;
    }

    public function getAction(): string
    {
        return 'pay';
    }

    public function getBody(): array
    {
        $order = $this->get_order();

        $this->ensureBuckarooFeeItem($order);

        $this->refreshOrderTotal($order);

        $body = array_merge(
            [
                'order' => (string) $order->get_id(),
                'invoice' => $this->get_invoice_number(),
                'amountDebit' => number_format((float) $order->get_total('edit'), 2, '.', ''),
                'currency' => get_woocommerce_currency(),
                'returnURL' => $this->get_return_url(),
                'cancelURL' => $this->get_return_url(),
                'pushURL' => $this->get_push_url(),
                'pushURLFailure' => $this->get_push_url(),
                'additionalParameters' => [
                    'real_order_id' => $order->get_id(),
                ],

                'description' => $this->get_description(),
                'clientIP' => $this->getIp(),
                'culture' => $this->determineCulture(),
            ],
            $this->getMethodBody(),
        );

        Logger::log(__METHOD__ . '|1|', [$_POST, $body]);

        return $body;
    }

    /**
     * Recompute and persist the order total from its existing line items
     * (products, shipping, fees and their taxes).
     *
     * `update_taxes()` rebuilds the order tax lines (and `cart_tax` /
     * `shipping_tax`) by summing the taxes ALREADY stored on each line item
     * and fee. It does not re-apply tax rates, so it never double-taxes
     * existing items, but it does roll in the tax of a Payment fee that was
     * added after the order's taxes were last calculated. This is required
     * because `calculate_totals(false)` derives the grand total from the
     * persisted `cart_tax`, which would otherwise omit that fee tax and make
     * amountDebit lower than the total the customer agreed to in checkout
     * (e.g. a Blocks Store API order created before the payment method, and
     * therefore the fee, was known).
     */
    protected function refreshOrderTotal(WC_Order $order): void
    {
        $previousTotal = (float) $order->get_total('edit');
        $order->update_taxes();
        $order->calculate_totals(false);
        if (abs((float) $order->get_total('edit') - $previousTotal) >= 0.01) {
            $order->save();
        }
    }

    /**
     * Materialize the gateway's configured Payment fee
     * (`extrachargeamount`) on the order when the cart -> order fee
     * transfer was skipped (e.g. Blocks Store API persisted the order
     * before `chosen_payment_method` was set). Mirrors the cart-side
     * logic in OrderActions::add_fee_to_cart so both paths produce the
     * same fee name, amount and tax handling.
     *
     * @return bool True when a fee item was added to the order.
     */
    protected function ensureBuckarooFeeItem(WC_Order $order): bool
    {
        $rawAmount = $this->gateway->get_option('extrachargeamount', 0);
        if (! is_scalar($rawAmount)) {
            return false;
        }

        $rawAmount = trim((string) $rawAmount);
        if ($rawAmount === '' || (float) $rawAmount === 0.0) {
            return false;
        }

        if (! preg_match('/^\d+(?:\.\d+)?%?$/', $rawAmount)) {
            return false;
        }

        $feeName = __('Payment fee', 'wc-buckaroo-bpe-gateway');

        foreach ($order->get_items('fee') as $existingFee) {
            if ($existingFee instanceof WC_Order_Item_Fee && $existingFee->get_name() === $feeName) {
                return false;
            }
        }

        $isPercentage = strpos($rawAmount, '%') !== false;
        $feeAmount = (float) str_replace('%', '', $rawAmount);
        if ($feeAmount === 0.0) {
            return false;
        }

        if ($isPercentage) {
            $subtotal = (float) $order->get_subtotal();
            $feeAmount = round($subtotal * $feeAmount / 100, 2);
        }

        $feeTaxClass = $this->gateway->get_option('feetax', '');
        $feeTaxClass = is_scalar($feeTaxClass) ? (string) $feeTaxClass : '';

        $item = new WC_Order_Item_Fee();
        $item->set_name($feeName);
        $item->set_amount((string) $feeAmount);
        $item->set_tax_class($feeTaxClass);
        $item->set_tax_status('taxable');
        $item->set_total((string) $feeAmount);
        $item->calculate_taxes($this->resolveTaxLocation($order));

        $order->add_item($item);
        $order->save();

        return true;
    }

    /**
     * Tax location used to compute the Payment fee tax. Falls back to the
     * billing address when the order has no shipping country, so the fee tax
     * is correctly stored (and later summed by update_taxes()) for orders
     * without a separate shipping address (e.g. virtual products).
     */
    private function resolveTaxLocation(WC_Order $order): array
    {
        $location = $order->get_address('shipping');

        if (empty($location['country'])) {
            $location = $order->get_address('billing');
        }

        return is_array($location) ? $location : [];
    }

    protected function getMethodBody(): array
    {
        return [];
    }

    /**
     * Get order
     */
    protected function get_order(): WC_Order
    {
        return $this->order_details->get_order();
    }

    private function get_invoice_number(): string
    {
        return $this->get_order()->get_order_number();
    }

    public function get_return_url($order = null): string
    {
        return add_query_arg('wc-api', 'WC_Gateway_' . ucfirst($this->gateway->id), home_url('/'));
    }

    /**
     * Get push url
     */
    private function get_push_url(): string
    {
        return add_query_arg('wc-api', 'wc_push_buckaroo', home_url('/'));
    }

    /**
     * Get the parsed label, we replace the template variables with the values
     */
    public function get_description(): string
    {
        $order = $this->get_order();
        $order_number = $order->get_order_number();
        $label = $this->gateway->get_option('transactiondescription', 'Order #' . $order->get_order_number());

        $label = str_replace('{order_number}', $order_number, $label);
        $label = str_replace('{shop_name}', get_bloginfo('name'), $label);

        $products = $order->get_items('line_item');
        if (count($products)) {
            $label = str_replace('{product_name}', reset($products)->get_name(), $label);
        }

        $label = preg_replace("/\r?\n|\r/", '', $label);

        $label = html_entity_decode($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return mb_substr($label, 0, 244);
    }

    /**
     * Get address component
     *
     * @param  string  $default
     * @return mixed
     */
    protected function getAddress(string $type, string $key, $default = '')
    {
        $value = $this->order_details->get($type . '_' . $key, $default);

        if (! $value && $type == 'shipping') {
            $value = $this->order_details->get('billing_' . $key, $default);
        }

        return $value;
    }

    /**
     * Get order articles
     */
    protected function getArticles(): array
    {
        return $this->order_articles->get_products_for_payment();
    }
}
