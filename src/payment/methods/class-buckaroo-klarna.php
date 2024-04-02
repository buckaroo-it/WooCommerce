<?php

class Buckaroo_Klarna extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return array_merge_recursive(
            $this->get_billing(),
            $this->get_shipping(),
            ['articles' =>$this->get_articles()]
        );
    }
    /**
     * @return array<mixed>
     */
    protected function get_billing(): array {
        $streetParts  = $this->order_details->get_billing_address_components();
        return  [
            'billing' => [
                'recipient' => [
                    'category'              => $this->get_category('billing'),
                    'firstName'             => $this->get_address('billing', 'first_name'),
                    'lastName'              => $this->get_address('billing', 'last_name'),
                    'gender'                => $this->request_string($this->gateway->getKlarnaSelector() . '-gender', 'male')
                ],
                'address' => [
                    'street'                => $streetParts->get_street(),
                    'houseNumber'           => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode'               => $this->get_address('billing', 'postcode'),
                    'city'                  => $this->get_address('billing', 'city'),
                    'country'               => $this->get_address('billing', 'country'),
                ],
                'phone' => [
                    'mobile'        => $this->get_phone($this->order_details->get_billing_phone()),
                ],
                'email'         => $this->get_address('billing', 'email')
            ]
        ];
    }

    /**
     * Get shipping address data
     *
     * @return array<mixed>
     */
    protected function get_shipping(): array {
        $streetParts  = $this->order_details->get_shipping_address_components();
        return  [
            'shipping' => [
                'recipient' => [
                    'category'              => $this->get_category('shipping'),
                    'firstName'             => $this->get_address('shipping', 'first_name'),
                    'lastName'              => $this->get_address('shipping', 'last_name'),
                    'gender'                => $this->request_string($this->gateway->getKlarnaSelector() . '-gender', 'male')
                ],
                'address' => [
                    'street'                => $streetParts->get_street(),
                    'houseNumber'           => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode'               => $this->get_address('shipping', 'postcode'),
                    'city'                  => $this->get_address('shipping', 'city'),
                    'country'               => $this->get_address('shipping', 'country'),
                ],
                'email'         => $this->get_address('shipping', 'email')
            ]
        ];
    }
    private function get_phone(string $phone): string
    {
        $input_phone = $this->order_details->cleanup_phone(
            $this->request($this->gateway->getKlarnaSelector() . "-phone")
        );
        if (strlen(trim($input_phone)) > 0) {
            return $input_phone;
        }
        return $phone;
    }

    /**
     * Get type of request b2b or b2c
     *
     * @return string
     */
    private function get_category(string $address_type): string
    {
        if (!$this->isCompanyEmpty($this->get_address($address_type, "company"))) {
            return 'B2B';
        }
        return 'B2C';
    }

    /**
     * Check if company is empty
     *
     * @param string $company
     *
     * @return boolean
     */
    public function isCompanyEmpty(string $company = null): bool
    {
        return null === $company || strlen(trim($company)) === 0;
    }
}
