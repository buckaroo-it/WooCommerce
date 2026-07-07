<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Services\Logger;

class ApplepayProcessor extends AbstractPaymentProcessor
{
    protected $data;

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        if ($this->getPaymentToken() === '') {
            // Diagnostic: log what actually reached the server (keys only, no
            // sensitive values) so a missing/renamed field or an empty hidden
            // input can be told apart from a failed client-side authorisation.
            $rawPaymentData = $this->request->input('paymentData');
            Logger::log(__METHOD__ . '|missing applepay token|', [
                'request_keys' => array_keys($this->request->all()),
                'paymentData_type' => gettype($rawPaymentData),
                'paymentData_keys' => is_array($rawPaymentData) ? array_keys($rawPaymentData) : null,
                'paymentData_length' => is_string($rawPaymentData) ? strlen($rawPaymentData) : null,
            ]);

            throw new \UnexpectedValueException(
                __(
                    'Apple Pay authorisation was not completed. Please try again or choose another payment method.',
                    'wc-buckaroo-bpe-gateway'
                )
            );
        }

        $paymentData = $this->normalize_payment_data($this->request->input('paymentData'));

        return array_merge(
            [
                'customerCardName' => $this->get_customer_name($paymentData),
                'paymentData' => $this->getPaymentToken(),
            ],
            $this->getBillingData(),
            $this->getShippingData()
        );
    }

    /**
     * The base64-encoded Apple Pay token, or an empty string when absent.
     */
    private function getPaymentToken(): string
    {
        return $this->get_payment_data(
            $this->normalize_payment_data($this->request->input('paymentData'))
        );
    }

    /**
     * Billing recipient data, sent as BillingCustomer service parameters.
     *
     * @return array<mixed>
     */
    protected function getBillingData(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();

        return [
            'billing' => [
                'recipient' => [
                    'category' => 'Person',
                    'firstName' => $this->getAddress('billing', 'first_name'),
                    'lastName' => $this->getAddress('billing', 'last_name'),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->getAddress('billing', 'postcode'),
                    'city' => $this->getAddress('billing', 'city'),
                    'state' => $this->getAddress('billing', 'state'),
                    'country' => $this->getAddress('billing', 'country'),
                ],
                'phone' => [
                    'mobile' => $this->order_details->get_billing_phone(),
                ],
                'email' => $this->getAddress('billing', 'email'),
            ],
        ];
    }

    /**
     * Shipping recipient data, sent as ShippingCustomer service parameters.
     * Falls back to billing values for fields the order has no shipping data for.
     *
     * @return array<mixed>
     */
    protected function getShippingData(): array
    {
        $streetParts = $this->order_details->get_shipping_address_components();

        return [
            'shipping' => [
                'recipient' => [
                    'category' => 'Person',
                    'firstName' => $this->getAddress('shipping', 'first_name'),
                    'lastName' => $this->getAddress('shipping', 'last_name'),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->getAddress('shipping', 'postcode'),
                    'city' => $this->getAddress('shipping', 'city'),
                    'state' => $this->getAddress('shipping', 'state'),
                    'country' => $this->getAddress('shipping', 'country'),
                ],
                'phone' => [
                    'mobile' => $this->order_details->get_shipping_phone(),
                ],
                'email' => $this->getAddress('shipping', 'email'),
            ],
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
