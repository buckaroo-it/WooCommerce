<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class ApplepayProcessor extends AbstractPaymentProcessor
{
    protected $data;

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

            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            // Keep values within Buckaroo's additionalParameter length limit.
            $filtered[$key] = mb_substr($value, 0, 128);
        }

        return $filtered;
    }

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        // Express posts the Apple payment object as a nested array; the standard
        // checkout method (classic + Blocks) posts it as a JSON string. Accept both.
        $paymentData = $this->normalize_payment_data($this->request->input('paymentData'));

        return [
            'customerCardName' => $this->get_customer_name($paymentData),
            'paymentData' => $this->get_payment_data($paymentData),
        ];
    }

    /**
     * Normalise the Apple Pay payment payload to an array.
     *
     * @param  mixed  $data
     * @return array
     */
    private function normalize_payment_data($data): array
    {
        if (is_string($data)) {
            $decoded = json_decode(wp_unslash($data), true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
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
