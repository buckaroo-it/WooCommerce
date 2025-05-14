<?php

namespace Buckaroo\Woocommerce\Gateways\SepaDirectDebit;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;

class SepaDirectDebitProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        if (
            $this->request->input('buckaroo-sepadirectdebit-accountname') !== null &&
            $this->request->input('buckaroo-sepadirectdebit-iban') !== null
        ) {
            return [
                'iban' => $this->request->input('buckaroo-sepadirectdebit-iban'),
                'customer' => [
                    'name' => $this->request->input('buckaroo-sepadirectdebit-accountname'),
                ],
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

    public function beforeReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        if ($responseParser->isSuccess()) {
            $params = $responseParser->get('Services.Service.ResponseParameter');
            $order = $this->get_order();

            if (! is_array($params)) {
                return;
            }
            foreach ($params as $param) {
                if ($param->Name === 'MandateReference') {
                    $order->add_order_note('MandateReference: ' . $param->_, 1);
                }
                if ($param->Name === 'MandateDate') {
                    $order->add_order_note('MandateDate: ' . $param->_, 1);
                }
            }
        }
    }
}
