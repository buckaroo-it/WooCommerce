<?php

class Buckaroo_In3_V2 extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return array_merge(
            [
                'invoiceDate'  => date('Y-m-d'),
                'customerType' => 'Debtor',
                'email'        =>  $this->get_address('billing', 'email'),
                'phone'        => [
                    'mobile' => $this->get_address('billing', 'phone')
                ],
            ],
            $this->get_customer(),
            $this->get_address_payload(),
            ['articles' => $this->get_articles()]
        );
    }

    /** @inheritDoc */
    public function get_action(): string
    {
        return 'payInInstallments';
    }

    /**
     * Get customer info
     *
     * @return array<mixed>
     */
    private function get_customer(): array
    {
        return [
            'customer' => [
                'initials'              => $this->order_details->get_initials($this->get_address('billing', 'last_name')),
                'lastName'              => $this->get_address('billing', 'last_name'),
                'email'                 => $this->get_address('billing', 'email'),
                'phone'                 => $this->get_address('billing', 'phone'),
                'culture'               => 'nl-NL',
                'birthDate'             => date('Y-m-d', strtotime($this->request_string('buckaroo-in3-birthdate'))),
            ]
        ];
    }

    /**
     * Get billing address data
     *
     * @return array<mixed>
     */
    private function get_address_payload(): array
    {
        $streetParts  = $this->order_details->get_billing_address_components();
        $country_code = $this->get_address('billing', 'country');

        $data = [
            'address' => [
                'street'                => $streetParts->get_street(),
                'houseNumber'           => $streetParts->get_house_number(),
                'zipcode'               => $this->get_address('billing', 'postcode'),
                'city'                  => $this->get_address('billing', 'city'),
                'country'               => $country_code,
            ]
        ];

        if (strlen($streetParts->get_number_additional()) > 0) {
            $data['address']['houseNumberAdditional'] = $streetParts->get_number_additional();
        }

        return $data;
    }
}
