<?php

namespace Buckaroo\Woocommerce\Gateways\SepaDirectDebit;

use Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class SepaDirectDebitProcessor extends AbstractPaymentProcessor
{
    /** @inheritDoc */
    protected function getMethodBody(): array
    {
        if (
            $this->request->input('buckaroo-sepadirectdebit-accountname') !== null &&
            $this->request->input('buckaroo-sepadirectdebit-iban') !== null
        ) {
            return [
                'iban' => $this->request->input('buckaroo-sepadirectdebit-iban'),
                'customer' => [
                    'name' => $this->request->input('buckaroo-sepadirectdebit-accountname')
                ]
            ];
        }
        return [];
    }

    public function afterProcessPayment(OrderDetails $orderDetails, TransactionResponse $transactionResponse): array
    {
        if ($transactionResponse->isSuccess() || $transactionResponse->isAwaitingConsumer() || $transactionResponse->isPendingProcessing()) {
            $params = $transactionResponse->getServiceParameters();
            $order = $orderDetails->get_order();

            $order->add_order_note('MandateReference: ' . $params['mandatereference'] ?? '', true);
            $order->add_order_note('MandateDate: ' . $params['mandatedate'] ?? '', true);
        }

        return [];
    }
}