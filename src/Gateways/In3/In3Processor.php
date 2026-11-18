<?php

namespace Buckaroo\Woocommerce\Gateways\In3;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Traits\HasDateValidation;

class In3Processor extends AbstractPaymentProcessor
{
    use HasDateValidation;

    protected function getMethodBody(): array
    {
        return array_merge(
            $this->getBilling(),
            $this->getShipping(),
            ['articles' => $this->getArticles()]
        );
    }

    private function getBilling(): array
    {
        $phone = $this->request->input('buckaroo-in3-phone');
        if (! is_string($phone) || trim($phone) === '') {
            $phone = $this->getAddress('billing', 'phone');
        }
        $birthDate = $this->parseBirthdate($this->request->input('buckaroo-in3-birthdate'));

        return [
            'billing' => [
                'recipient' => [
                    'category' => 'B2C',
                    'initials' => $this->order_details->get_initials(
                        $this->order_details->get_full_name()
                    ),
                    'firstName' => $this->getAddress('billing', 'first_name'),
                    'lastName' => $this->getAddress('billing', 'last_name'),
                    'birthDate' => $birthDate !== null ? $birthDate->format('Y-m-d') : null,
                    'customerNumber' => get_current_user_id() ?? '',
                    'phone' => $phone,
                    'country' => $this->getAddress('billing', 'country'),
                ],
                'email' => $this->getAddress('billing', 'email'),
                'phone' => [
                    'phone' => $phone,
                ],
                'address' => $this->getAddressPayload('billing'),
            ],
        ];
    }

    /**
     * Get billing address data
     *
     * @return array<mixed>
     */
    private function getAddressPayload(string $address_type): array
    {
        $streetParts = $this->order_details->get_billing_address_components();
        $country_code = $this->getAddress($address_type, 'country');

        $data = [
            'street' => $streetParts->get_street(),
            'houseNumber' => $streetParts->get_house_number(),
            'zipcode' => $this->getAddress($address_type, 'postcode'),
            'city' => $this->getAddress($address_type, 'city'),
            'country' => $country_code,
        ];

        if (strlen($streetParts->get_number_additional()) > 0) {
            $data['houseNumberAdditional'] = $streetParts->get_number_additional();
        }

        return $data;
    }

    /**
     * Get customer info
     *
     * @return array<mixed>
     */
    private function getShipping(): array
    {
        return [
            'shipping' => [
                'recipient' => [
                    'category' => 'B2C',
                    'firstName' => $this->getAddress('shipping', 'first_name'),
                    'lastName' => $this->getAddress('shipping', 'last_name'),
                    'careOf' => $this->order_details->get_full_name(),
                ],
                'address' => $this->getAddressPayload('shipping'),
            ],
        ];
    }

    public function unsuccessfulReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        if ($responseParser->isPendingProcessing()) {
            return [
                'result' => 'failure',
                'redirect' => $redirectUrl,
            ];
        }

        return false;
    }
}
