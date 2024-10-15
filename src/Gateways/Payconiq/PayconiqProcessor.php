<?php

namespace Buckaroo\Woocommerce\Gateways\Payconiq;

use Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class PayconiqProcessor extends AbstractPaymentProcessor
{
    public function afterProcessPayment(OrderDetails $orderDetails, TransactionResponse $transactionResponse)
    {
        $key = $transactionResponse->getTransactionKey();
        $invoiceNumber = $transactionResponse->getInvoice();
        $amount = $transactionResponse->getAmount();
        return array(
            'result' => 'success',
            'redirect' => home_url('/') . 'payconiqQrcode?' .
                "transactionKey=" . $key .
                "&invoicenumber=" . $invoiceNumber .
                "&amount=" . $amount .
                "&returnUrl=" . add_query_arg('wc-api', 'WC_Gateway_' . ucfirst($this->id), home_url('/')) .
                "&order_id=" . (int)$orderDetails->get_order()->get_id() .
                "&currency=" . $orderDetails->get_order()->get_currency(),
        );
    }

}