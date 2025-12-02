<?php

namespace Buckaroo\Woocommerce\Gateways\Transfer;

use Buckaroo\Woocommerce\ResponseParser\IGatewayResponse;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

class TransferResponse implements IGatewayResponse
{
    protected ResponseParser $responseParser;

    public function __construct(ResponseParser $responseParser)
    {
        $this->responseParser = $responseParser;
        $this->updateMeta();
    }

    protected function updateMeta(): void
    {
        $order_id = $this->getOrderId($this->responseParser);

        if (empty($order_id)) {
            return;
        }

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

                    $name  = strtolower($parameter['name']);
                    $value = $parameter['value'];

                    switch ($name) {
                        case 'bic':
                            update_post_meta($order_id, 'buckaroo_BIC', $value);
                            break;
                        case 'iban':
                            update_post_meta($order_id, 'buckaroo_IBAN', $value);
                            break;
                        case 'accountholdername':
                            update_post_meta($order_id, 'buckaroo_accountHolderName', $value);
                            break;
                        case 'bankaccount':
                            update_post_meta($order_id, 'buckaroo_bankAccount', $value);
                            break;
                        case 'accountholdercity':
                            update_post_meta($order_id, 'buckaroo_accountHolderCity', $value);
                            break;
                        case 'accountholdercountry':
                            update_post_meta($order_id, 'buckaroo_accountHolderCountry', $value);
                            break;
                        case 'paymentreference':
                            update_post_meta($order_id, 'buckaroo_paymentReference', $value);
                            break;
                    }
                }
            }

            return;
        }

        if ($val = $this->responseParser->getService('bic')) {
            update_post_meta($order_id, 'buckaroo_BIC', $val);
        }

        if ($val = $this->responseParser->getService('iban')) {
            update_post_meta($order_id, 'buckaroo_IBAN', $val);
        }

        if ($val = $this->responseParser->getService('accountholdername')) {
            update_post_meta($order_id, 'buckaroo_accountHolderName', $val);
        }

        if ($val = $this->responseParser->getService('bankaccount')) {
            update_post_meta($order_id, 'buckaroo_bankAccount', $val);
        }

        if ($val = $this->responseParser->getService('accountholdercity')) {
            update_post_meta($order_id, 'buckaroo_accountHolderCity', $val);
        }

        if ($val = $this->responseParser->getService('accountholdercountry')) {
            update_post_meta($order_id, 'buckaroo_accountHolderCountry', $val);
        }

        if ($val = $this->responseParser->getService('paymentreference')) {
            update_post_meta($order_id, 'buckaroo_paymentReference', $val);
        }
    }

    /**
     * Resolve the WooCommerce order ID from the response, following the same
     * strategy as the generic ReturnProcessor.
     */
    protected function getOrderId(ResponseParser $responseParser): ?string
    {
        $orderId = $responseParser->getOrderNumber() ?: $responseParser->getInvoice();
        if (! is_null($responseParser->getRealOrderId())) {
            $orderId = $responseParser->getRealOrderId();
        }

        return $orderId;
    }

    public function toResponse(): ResponseParser
    {
        return $this->responseParser;
    }
}
