<?php

class Buckaroo_Return_Processor
{
    private Buckaroo_Order_Details $order_details;

    private WC_Gateway_Buckaroo $gateway;

    public function __construct(WC_Gateway_Buckaroo $gateway, int $order_id)
    {
        $this->order_details = new Buckaroo_Order_Details(new WC_Order($order_id));
        $this->gateway = $gateway;
    }

    public function process(
        Buckaroo_Sdk_Response $response
    ) {
        $message = __('Payment unsuccessful. Please try again or choose another payment method.',  'wc-buckaroo-bpe-gateway');

        if (
            $response->is_success() ||
            $response->is_awaiting_consumer() ||
            $response->is_pending_processing()
        ) {
            $this->update_order_meta($response);
        }
        if ($response->has_redirect()) {
            if ($this->is_payconiq()) {
                return $this->redirect_to_payconiq($response);
            }
            return [
                'result'   => 'success',
                'redirect' => $response->get_redirect_url(),
            ];
        }

        if (
            $response->is_success() ||
            $response->is_awaiting_consumer() ||
            $response->is_pending_processing()
        ) {
            return [
                'result'   => 'success',
                'redirect' => $this->gateway->get_return_url($this->order_details->get_order()),
            ];
        }

        if ($response->is_canceled()) {
            $this->set_order_status('cancelled', $response->get_some_error());
            return $this->failed(__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'));
        }

        $this->set_order_status('failed', $response->get_some_error());

        if ($this->is_afterpay()) {
            $message = __(
                "We are sorry to inform you that the request to pay afterwards with Riverty | AfterPay is not possible at this time. This can be due to various (temporary) reasons. For questions about your rejection you can contact the customer service of Riverty | AfterPay. Or you can visit the website of Riverty | AfterPay and check the 'Frequently asked questions' through this <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord\" target=\"_blank\">link</a>. We advise you to choose another payment method to complete your order.",
                'wc-buckaroo-bpe-gateway'
            );
        }

        return $this->failed($message);
    }

    private function update_order_meta(Buckaroo_Sdk_Response $response)
    {
        $order = $this->order_details->get_order();

        update_post_meta(
            $order->get_id(),
            '_buckaroo_order_in_test_mode',
            $response->is_test_mode() == true
        );

        if ($this->is_sepa()) {
            $params = $response->get_service_parameters();

            $order->add_order_note('MandateReference: ' . $params['mandatereference'] ?? '', true);
            $order->add_order_note('MandateDate: ' . $params['mandatedate'] ?? '', true);
        }
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
            'result'   => 'error',
            'redirect' => wc_get_checkout_url(),
        ];
    }

    private function is_sepa(): bool
    {
        return $this->gateway->id === 'buckaroo_sepadirectdebit';
    }

    private function is_afterpay(): bool
    {
        return $this->gateway->id === 'buckaroo_afterpay';
    }

    private function is_payconiq(): bool
    {
        return $this->gateway->id === 'buckaroo_payconiq';
    }

    private function redirect_to_payconiq(Buckaroo_Sdk_Response $response): array
    {
        $key = $response->get_transaction_key();
        $invoiceNumber = $response->get('Invoice');
        $amount        = $response->get('AmountDebit');
        return array(
            'result'   => 'success',
            'redirect' => home_url('/') . 'payconiqQrcode?' .
                "transactionKey=" . $key .
                "&invoicenumber=" . $invoiceNumber .
                "&amount=" . $amount .
                "&returnUrl=" . add_query_arg('wc-api', 'wc_buckaroo_return', home_url('/')) .
                "&order_id=" . (int) $this->order_details->get_order()->get_id() .
                "&currency=" . $this->order_details->get_order()->get_currency(),
        );
    }
}
