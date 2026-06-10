<?php

namespace Buckaroo\Woocommerce\Gateways;

use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\Request;
use WC_Order;

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

        // Ensure the persisted order total reflects every line item (incl. the
        // Buckaroo "Payment fee"). In some Blocks Store API flows the cart's
        // _fees_hash caching can leave the order with fee line items present
        // but a stale total that does not include them, which then makes
        // amountDebit and the success-page total disagree with the article list.
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
     * Recompute the order total from its existing line items (products,
     * shipping, fees, taxes) and persist the result when it changed.
     *
     * `calculate_totals(false)` does NOT re-apply tax rates, it just sums the
     * subtotal/shipping/fee/tax that are already stored on each line item, so
     * it can safely run after the order has been created without double-taxing
     * anything. We only call `save()` when the total actually changed, so this
     * is a no-op for orders that were already in sync.
     */
    protected function refreshOrderTotal(WC_Order $order): void
    {
        $previousTotal = (float) $order->get_total('edit');
        $order->calculate_totals(false);
        if (abs((float) $order->get_total('edit') - $previousTotal) >= 0.01) {
            $order->save();
        }
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
