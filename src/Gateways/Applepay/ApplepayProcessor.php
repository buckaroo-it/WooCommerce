<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class ApplepayProcessor extends AbstractPaymentProcessor
{
    protected $data;

    /**
     * Buckaroo rejects additionalParameter values longer than 50 characters,
     * so every value is sanitized and truncated to this limit.
     */
    private const ADDITIONAL_PARAMETER_MAX_LENGTH = 50;

    /** {@inheritDoc} */
    public function getBody(): array
    {
        $body = parent::getBody();

        $shippingParameters = $this->getShippingAdditionalParameters();
        if (! empty($shippingParameters)) {
            $existing = (isset($body['additionalParameters']) && is_array($body['additionalParameters']))
                ? $body['additionalParameters']
                : [];
            $body['additionalParameters'] = array_merge($existing, $shippingParameters);
        }

        return $body;
    }

    /**
     * Build the shipping details as additionalParameters so the chosen
     * shipping method, cost and destination are carried into the Buckaroo
     * transaction. Apple Pay is a card/wallet "pay" service that has no
     * dedicated shipping block (unlike pay-later services), so
     * additionalParameters is the service-agnostic channel for this data.
     *
     * @return array<string, string>
     */
    protected function getShippingAdditionalParameters(): array
    {
        $order = $this->get_order();

        $parameters = [
            'shipping_method' => $order->get_shipping_method(),
            'shipping_cost' => number_format(
                (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(),
                2,
                '.',
                ''
            ),
            'shipping_name' => trim(
                $this->getAddress('shipping', 'first_name') . ' ' . $this->getAddress('shipping', 'last_name')
            ),
            'shipping_street' => trim(
                $this->getAddress('shipping', 'address_1') . ' ' . $this->getAddress('shipping', 'address_2')
            ),
            'shipping_zipcode' => $this->getAddress('shipping', 'postcode'),
            'shipping_city' => $this->getAddress('shipping', 'city'),
            'shipping_country' => $this->getAddress('shipping', 'country'),
        ];

        $filtered = [];
        foreach ($parameters as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            // Collapse whitespace / strip control characters, then enforce the
            // Buckaroo additionalParameter length limit.
            $value = trim((string) $value);
            $value = preg_replace('/\s+/', ' ', $value);
            $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

            if ($value === '') {
                continue;
            }

            $filtered[$key] = mb_substr($value, 0, self::ADDITIONAL_PARAMETER_MAX_LENGTH);
        }

        return $filtered;
    }

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        return [
            'customerCardName' => $this->get_customer_name($this->request->input('paymentData')),
            'paymentData' => $this->get_payment_data($this->request->input('paymentData')),
        ];
    }

    /**
     * @param  mixed  $data
     */
    private function get_customer_name($data): string
    {
        if (
            isset($data['billingContact']) &&
            isset($data['billingContact']['givenName']) &&
            isset($data['billingContact']['familyName'])
        ) {
            return $data['billingContact']['givenName'] . ' ' . $data['billingContact']['familyName'];
        }

        return '';
    }

    /**
     * @param  mixed  $data
     */
    private function get_payment_data($data): string
    {
        if (! isset($data['token']) || empty($data['token'])) {
            return '';
        }

        return base64_encode(json_encode($data['token']));
    }
}
