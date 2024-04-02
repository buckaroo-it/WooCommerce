<?php

class Buckaroo_In3 extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {

        return array_merge(
            $this->get_billing(),
            $this->get_shipping(),
            ['articles' =>$this->get_articles()]
        );
    }

    private function get_billing(): array
    {
        $phone = $this->request('buckaroo-in3-phone', $this->get_address('billing', 'phone'));
        return [
            'billing' => [
                'recipient' => [
                    'category'      =>  'B2C',
                    'initials'      => $this->order_details->get_initials(
                        $this->order_details->get_full_name()
                    ),
                    'firstName'     => $this->get_address('billing', 'first_name'),
                    'lastName'      => $this->get_address('billing', 'last_name'),
                    'birthDate'     => date('Y-m-d', strtotime($this->request('buckaroo-in3-birthdate'))),
                    'customerNumber' => get_current_user_id(),
                    'phone'         => $phone,
                    'country'       => $this->get_address('billing', 'country')
                ],
                'email' => $this->get_address('billing', 'email'),
                'phone' => [
                    'phone' => $phone,
                ],
                'address' => $this->get_address_payload('shipping')
            ]
        ];
    }



    /**
     * Get customer info
     *
     * @return array<mixed>
     */
    private function get_shipping(): array
    {
        return [
            'shipping' => [
                'recipient' => [
                    'category'      => 'B2C',
                    'firstName'     => $this->get_address('shipping', 'first_name'),
                    'lastName'      => $this->get_address('shipping', 'last_name'),
                    'careOf'        => $this->order_details->get_full_name()
                ],
                'address' => $this->get_address_payload('shipping')
            ]
        ];
    }

    /**
     * Get billing address data
     *
     * @return array<mixed>
     */
    private function get_address_payload(string $address_type): array
    {
        $streetParts  = $this->order_details->get_billing_address_components();
        $country_code = $this->get_address($address_type, 'country');

        $data = [
            'address' => [
                'street'                => $streetParts->get_street(),
                'houseNumber'           => $streetParts->get_house_number(),
                'zipcode'               => $this->get_address($address_type, 'postcode'),
                'city'                  => $this->get_address($address_type, 'city'),
                'country'               => $country_code,
            ]
        ];

        if (strlen($streetParts->get_number_additional()) > 0) {
            $data['address']['houseNumberAdditional'] = $streetParts->get_number_additional();
        }

        return $data;
    }
}
