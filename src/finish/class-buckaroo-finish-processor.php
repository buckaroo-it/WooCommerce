<?php

namespace WC_Buckaroo\WooCommerce\Finish;

use WC_Buckaroo\WooCommerce\Payment\Buckaroo_Order_Details;
use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentGatewayHandler;
use WC_Order;

class Buckaroo_Finish_Processor
{
    private Buckaroo_Order_Details $order_details;

    /** @var PaymentGatewayHandler|null */
    private $gateway;

    public function __construct()
    {
    }

    public function process(
        Buckaroo_Return_Payload $response
    )
    {
        $message = __('Payment unsuccessful. Please try again or choose another payment method.', 'wc-buckaroo-bpe-gateway');

        $order_id = $response->get_order_id();
        if ($order_id === null) {
            return $this->failed(__('Could not process payment', 'wc-buckaroo-bpe-gateway'));
        }

        $this->init($order_id);

        if ($this->gateway === null) {
            return $this->failed(__('Could not find payment method', 'wc-buckaroo-bpe-gateway'));
        }

        if ($this->is_paypal() && $response->is_pending_processing()) {
            return $this->failed($message);
        }

        if (
            $response->is_success()
        ) {
            return [
                'result' => 'success',
                'redirect' => $this->gateway->get_return_url($this->order_details->get_order()),
            ];
        }

        if ($response->is_cancelled()) {
            $this->set_order_status('cancelled', $response->get_error_message());
            return $this->failed(__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'));
        }

        $this->set_order_status('failed', $response->get_error_message());

        if ($this->is_afterpay()) {
            $message = __(
                "We are sorry to inform you that the request to pay afterwards with Riverty | AfterPay is not possible at this time. This can be due to various (temporary) reasons. For questions about your rejection you can contact the customer service of Riverty | AfterPay. Or you can visit the website of Riverty | AfterPay and check the 'Frequently asked questions' through this <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord\" target=\"_blank\">link</a>. We advise you to choose another payment method to complete your order.",
                'wc-buckaroo-bpe-gateway'
            );
        }

        return $this->failed($message);
    }

    private function init(int $order_id)
    {
        $order = new WC_Order($order_id);
        $this->order_details = new Buckaroo_Order_Details($order);
        $this->gateway = $this->get_gateway($order->get_payment_method('edit'));
    }

    private function get_gateway(string $payment_method_id): ?PaymentGatewayHandler
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        return $gateways[$payment_method_id] ?? null;
    }

    private function set_order_status(string $status, string $message = '')
    {
        if ($this->order_details->get_order() === "pending") {
            return $this->order_details->get_order()->update_status($status, $message);
        }

        if (strlen($message)) {
            return $this->order_details->get_order()->add_order_note($message);
        }
    }

    /**
     * Redirect back to checkout with message
     *
     * @param string $message
     *
     * @return array
     */
    private function failed(string $message): array
    {
        wc_add_notice($message, 'error');
        return [
            'result' => 'error',
            'redirect' => wc_get_checkout_url(),
        ];
    }

    private function is_paypal(): bool
    {
        return $this->gateway->id === 'buckaroo_paypal';
    }

    private function is_afterpay(): bool
    {
        return $this->gateway->id === 'buckaroo_afterpay';
    }
}
