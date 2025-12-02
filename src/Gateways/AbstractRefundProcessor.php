<?php

namespace Buckaroo\Woocommerce\Gateways;

use Buckaroo\Woocommerce\Order\OrderDetails;
use WC_Order;

class AbstractRefundProcessor extends AbstractProcessor
{
    protected OrderDetails $order_details;

    private float $amount;

    private string $reason;

    private array $line_items;

    public function __construct(
        AbstractPaymentGateway $gateway,
        OrderDetails $order_details,
        float $amount,
        string $reason,
        array $line_items = []
    ) {
        $this->gateway = $gateway;
        $this->order_details = $order_details;
        $this->amount = $amount;
        $this->reason = $reason;
        $this->line_items = $line_items;
    }

    /**
     * Get request action
     */
    public function getAction(): string
    {
        return 'refund';
    }

    /**
     * Get request body
     */
    public function getBody(): array
    {
        $originalTransactionKey = (string) $this->getOrder()->get_transaction_id('edit');

        $captures = get_post_meta($this->getOrder()->get_id(), '_wc_order_captures');
        if ($captures && !empty($captures)) {
            $lastCapture = end($captures);
            if (isset($lastCapture['transaction_id'])) {
                $originalTransactionKey = $lastCapture['transaction_id'];
            }
        }

        return array_merge(
            $this->getMethodBody(),
            [
                'order' => (string) $this->getOrder()->get_id(),
                'invoice' => $this->get_invoice_number(),
                'amountCredit' => number_format((float) $this->amount, 2, '.', ''),
                'currency' => get_woocommerce_currency(),
                'returnURL' => $this->get_return_url(),
                'cancelURL' => $this->get_return_url(),
                'pushURL' => $this->get_push_url(),
                'pushURLFailure' => $this->get_push_url(),
                'originalTransactionKey' => $originalTransactionKey,
                'additionalParameters' => [
                    'real_order_id' => $this->getOrder()->get_id(),
                ],

                'description' => $this->get_description(),
                'clientIP' => $this->getIp(),
            ]
        );
    }

    protected function getMethodBody(): array
    {
        return [];
    }

    /**
     * Get order
     */
    protected function getOrder(): WC_Order
    {
        return $this->order_details->get_order();
    }

    private function get_invoice_number(): string
    {
        if (in_array($this->gateway->id, ['buckaroo_afterpaynew', 'buckaroo_afterpay'])) {
            return (string) $this->getOrder()->get_order_number() . time();
        }

        return (string) $this->getOrder()->get_order_number();
    }

    /**
     * Get return url
     *
     * @return string
     */
    public function get_return_url($order_id = null)
    {
        return add_query_arg('wc-api', 'wc_buckaroo_return', home_url('/'));
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
        $order = $this->getOrder();
        $order_number = $order->get_order_number();
        $label = $this->gateway->get_option('refund_description', 'Order #' . $order->get_order_number());

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
     * Get refunded line items
     */
    protected function getRefundedLineItems(): array
    {
        return $this->line_items;
    }
}
