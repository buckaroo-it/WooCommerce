<?php

namespace Buckaroo\Woocommerce\Gateways\In3;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class In3V2Processor extends AbstractPaymentProcessor
{
    protected function getMethodBody(): array
    {
        return array_merge(
            [
                'invoiceDate' => date('Y-m-d'),
                'customerType' => 'Debtor',
                'email' => $this->getAddress('billing', 'email'),
                'phone' => [
                    'mobile' => $this->getAddress('billing', 'phone'),
                ],
            ],
            $this->getCustomer(),
            $this->getAddressPayload(),
            ['articles' => $this->getArticles()]
        );
    }

    /**
     * Get customer info
     *
     * @return array<mixed>
     */
    private function getCustomer(): array
    {
        return [
            'customer' => [
                'initials' => $this->order_details->get_initials($this->getAddress('billing', 'last_name')),
                'lastName' => $this->getAddress('billing', 'last_name'),
                'email' => $this->getAddress('billing', 'email'),
                'phone' => $this->getAddress('billing', 'phone'),
                'culture' => 'nl-NL',
                'birthDate' => date('Y-m-d', strtotime($this->request->input('buckaroo-in3-birthdate'))),
            ],
        ];
    }

    /**
     * Get billing address data
     *
     * @return array<mixed>
     */
    private function getAddressPayload(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();
        $country_code = $this->getAddress('billing', 'country');

        $data = [
            'address' => [
                'street' => $streetParts->get_street(),
                'houseNumber' => $streetParts->get_house_number(),
                'zipcode' => $this->getAddress('billing', 'postcode'),
                'city' => $this->getAddress('billing', 'city'),
                'country' => $country_code,
            ],
        ];

        if (strlen($streetParts->get_number_additional()) > 0) {
            $data['address']['houseNumberAdditional'] = $streetParts->get_number_additional();
        }

        return $data;
    }

    /**
     * Get order articles
     */
    protected function getArticles(): array
    {
        return array_map(
            function ($article) {
                unset($article['vatPercentage']);

                return $article;
            },
            $this->order_articles->get_products_for_payment()
        );
    }
}
