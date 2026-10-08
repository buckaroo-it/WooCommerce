<?php

namespace Buckaroo\Woocommerce\Gateways\Transfer;

use Buckaroo\Woocommerce\ResponseParser\IGatewayResponse;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use WC_Order;

class TransferResponse implements IGatewayResponse
{
    protected ResponseParser $responseParser;

    public function __construct(ResponseParser $responseParser)
    {
        $this->responseParser = $responseParser;
    }

    /**
     * Store the bank transfer details on the order.
     * Only call this with a validated reply for the order it was resolved to.
     */
    public function storeBankDetails(WC_Order $order): void
    {
        if ($order->get_payment_method() !== 'buckaroo_transfer') {
            return;
        }

        $paymentMethod = $this->responseParser->getPaymentMethod();
        if (! is_string($paymentMethod) || strtolower($paymentMethod) !== 'transfer') {
            return;
        }

        $fields = $this->extractBankFields();

        if (empty($fields)) {
            return;
        }

        foreach ($fields as $metaKey => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $order->update_meta_data($metaKey, $value);
        }

        $order->save();
    }

    protected function extractBankFields(): array
    {
        $metaMap = [
            'bic' => 'buckaroo_BIC',
            'iban' => 'buckaroo_IBAN',
            'accountholdername' => 'buckaroo_accountHolderName',
            'bankaccount' => 'buckaroo_bankAccount',
            'accountholdercity' => 'buckaroo_accountHolderCity',
            'accountholdercountry' => 'buckaroo_accountHolderCountry',
            'paymentreference' => 'buckaroo_paymentReference',
        ];

        $fields = [];

        $services = $this->responseParser->get('services');
        if (is_array($services)) {
            foreach ($services as $service) {
                if (empty($service['parameters']) || ! is_array($service['parameters'])) {
                    continue;
                }
                foreach ($service['parameters'] as $parameter) {
                    if (! isset($parameter['name'], $parameter['value'])) {
                        continue;
                    }
                    $name = strtolower($parameter['name']);
                    if (isset($metaMap[$name])) {
                        $fields[$metaMap[$name]] = $parameter['value'];
                    }
                }
            }

            return $fields;
        }

        foreach ($metaMap as $serviceKey => $metaKey) {
            $value = $this->responseParser->getService($serviceKey);
            if (! empty($value)) {
                $fields[$metaKey] = $value;
            }
        }

        return $fields;
    }

    public function toResponse(): ResponseParser
    {
        return $this->responseParser;
    }
}
