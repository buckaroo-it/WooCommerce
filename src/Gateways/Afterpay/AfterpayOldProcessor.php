<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class AfterpayOldProcessor extends AbstractPaymentProcessor
{
    public function getAction(): string
    {
        if ($this->isAuthorization()) {
            return 'Authorize';
        }
        return parent::getAction();
    }

    private function isAuthorization(): bool
    {
        return $this->gateway->get_option('afterpaypayauthorize', 'pay') === 'authorize';
    }

    protected function getMethodBody(): array
    {
        return array_merge_recursive(
            [
                'customerIPAddress' => $this->getIp()
            ],
            $this->getBillingData(),
            $this->getShippingData(),
            ['articles' => $this->getArticles()]
        );
    }

    protected function getBillingData(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();
        $country_code = $this->getAddress('billing', 'country');
        $data = [
            'billing' => [
                'recipient' => [
                    'firstName' => $this->getAddress('billing', 'first_name'),
                    'lastName' => $this->getAddress('billing', 'last_name'),
                    'initials' => $this->order_details->get_initials(
                        $this->order_details->get_full_name('shipping')
                    ),
                    'culture' => $country_code
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->getAddress('billing', 'postcode'),
                    'city' => $this->getAddress('billing', 'city'),
                    'country' => $country_code,
                ],
                'phone' => [
                    'mobile' => $this->getPhone($this->order_details->get_billing_phone()),
                ],
                'email' => $this->getAddress('billing', 'email')
            ]
        ];
        return array_merge_recursive(
            $data,
            $this->getBirthDate($country_code)
        );
    }

    private function getPhone(string $phone): string
    {
        return $this->request->input('buckaroo-afterpaynew-phone', $phone);
    }

    protected function getBirthDate(string $country_code, string $type = 'billing'): array
    {
        if (in_array($country_code, ['NL', 'BE'])) {
            return [
                $type => [
                    'recipient' => [
                        'birthDate' => $this->getFormatedDate(),
                    ]
                ]
            ];
        }
        return [];
    }

    private function getFormatedDate()
    {

        $dateString = $this->request->input('buckaroo-afterpaynew-birthdate');
        if (!is_scalar($dateString)) {
            return null;
        }
        $date = strtotime((string)$dateString);
        if ($date === false) {
            return null;
        }

        return @date("d-m-Y", $date);
    }

    protected function getShippingData(): array
    {
        $streetParts = $this->order_details->get_shipping_address_components();
        $country_code = $this->getAddress('shipping', 'country');

        $data = [
            'shipping' => [
                'recipient' => [
                    'firstName' => $this->getAddress('shipping', 'first_name'),
                    'lastName' => $this->getAddress('shipping', 'last_name'),
                    'initials' => $this->order_details->get_initials(
                        $this->order_details->get_full_name('shipping')
                    )
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->getAddress('shipping', 'postcode'),
                    'city' => $this->getAddress('shipping', 'city'),
                    'country' => $country_code,
                ],
            ],
        ];
        return array_merge_recursive(
            $data,
            $this->getBirthDate($country_code, 'shipping')
        );
    }

    protected function getArticles(): array
    {
        return $this->order_articles->get_products_for_payment();
    }

}