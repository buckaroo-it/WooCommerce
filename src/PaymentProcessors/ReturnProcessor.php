<?php

namespace Buckaroo\Woocommerce\PaymentProcessors;


use Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Woocommerce\Components\OrderDetails;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use WC_Order;
use WP_Error;

class ReturnProcessor
{

    private WC_Order $order;
    private OrderDetails $order_details;

    private AbstractPaymentGateway $gateway;

    public function __construct(AbstractPaymentGateway $gateway, int $order_id)
    {
        $this->order = new WC_Order($order_id);
        $this->order_details = new OrderDetails($this->order);
        $this->gateway = $gateway;
    }

    public function paymentProcess(
        TransactionResponse $response
    ): array
    {
        $message = __('Payment unsuccessful. Please try again or choose another payment method.', 'wc-buckaroo-bpe-gateway');

        if ($response->isSuccess() || $response->isAwaitingConsumer() || $response->isPendingProcessing()) {
            $this->update_order_meta($response);
        }

        if ($response->hasRedirect()) {
            if ($this->is_payconiq()) {
                return $this->redirect_to_payconiq($response);
            }
            return [
                'result' => 'success',
                'redirect' => $response->getRedirectUrl(),
            ];
        }

        if (
            $response->isSuccess() ||
            $response->isAwaitingConsumer() ||
            $response->isPendingProcessing()
        ) {
            return [
                'result' => 'success',
                'redirect' => $this->gateway->get_return_url($this->order_details->get_order()),
            ];
        }

        if ($response->isCanceled()) {
            $this->set_order_status('cancelled', $response->getSomeError());
            return $this->failed(__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'));
        }

        $this->set_order_status('failed', $response->getSomeError());

        if ($this->is_afterpay()) {
            $message = __(
                "We are sorry to inform you that the request to pay afterwards with Riverty | AfterPay is not possible at this time. This can be due to various (temporary) reasons. For questions about your rejection you can contact the customer service of Riverty | AfterPay. Or you can visit the website of Riverty | AfterPay and check the 'Frequently asked questions' through this <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord\" target=\"_blank\">link</a>. We advise you to choose another payment method to complete your order.",
                'wc-buckaroo-bpe-gateway'
            );
        }

        return $this->failed($message);
    }

    private function update_order_meta(TransactionResponse $response)
    {
        $order = $this->order_details->get_order();

        update_post_meta(
            $order->get_id(),
            '_buckaroo_order_in_test_mode',
            $response->get('IsTest') == true
        );

        if ($this->is_sepa()) {
            $params = $response->getServiceParameters();

            $order->add_order_note('MandateReference: ' . $params['mandatereference'] ?? '', true);
            $order->add_order_note('MandateDate: ' . $params['mandatedate'] ?? '', true);
        }
    }

    private function is_sepa(): bool
    {
        return $this->gateway->id === 'buckaroo_sepadirectdebit';
    }

    private function is_payconiq(): bool
    {
        return $this->gateway->id === 'buckaroo_payconiq';
    }

    private function redirect_to_payconiq(TransactionResponse $response): array
    {
        $key = $response->getTransactionKey();
        $invoiceNumber = $response->get('Invoice');
        $amount = $response->get('AmountDebit');
        return array(
            'result' => 'success',
            'redirect' => home_url('/') . 'payconiqQrcode?' .
                "transactionKey=" . $key .
                "&invoicenumber=" . $invoiceNumber .
                "&amount=" . $amount .
                "&returnUrl=" . add_query_arg('wc-api', 'WC_Gateway_' . ucfirst($this->gateway->id), home_url('/')) .
                "&order_id=" . (int)$this->order_details->get_order()->get_id() .
                "&currency=" . $this->order_details->get_order()->get_currency(),
        );
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

    private function is_afterpay(): bool
    {
        return $this->gateway->id === 'buckaroo_afterpay';
    }

    public function refundProcess(TransactionResponse $response)
    {
        if ($response->isSuccess()) {
            $this->order->add_order_note(
                sprintf(
                    __('Refunded %1$s - Refund transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                    wc_price($response->get('AmountCredit')),
                    $response->getTransactionKey()
                )
            );
            add_post_meta(
                $this->order->get_id(),
                '_refundbuckaroo' . $response->getTransactionKey(),
                'ok',
                true
            );
            return true;
        }


        $this->order->add_order_note(
            sprintf(
                __(
                    'Refund failed for transaction ID: %s ' . "\n" . $response->getSomeError(),
                    'wc-buckaroo-bpe-gateway'
                ),
                $this->order->get_transaction_id()
            )
        );

        return new WP_Error('error_refund', __("Refund failed: ") . $response->getSomeError());
    }
}