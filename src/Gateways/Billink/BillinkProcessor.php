<?php

namespace Buckaroo\Woocommerce\Gateways\Billink;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class BillinkProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    public function getAction(): string
    {
        return parent::getAction();
    }

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        return array_merge_recursive(
            $this->getVatnumber(),
            $this->getCoc(),
            $this->getBillingData(),
            $this->getShippingData(),
            ['articles' => $this->getArticles()]
        );
    }

    /**
     * Get vat number
     *
     * @return array<mixed>
     */
    protected function getVatnumber(): array
    {
        $vatNumber = $this->request->input('buckaroo-billink-VatNumber');
        if (
            is_string($vatNumber) &&
            ! empty(trim($vatNumber))
        ) {
            return ['vATNumber' => $vatNumber];
        }

        return [];
    }

    /**
     * Get chamber of commerce number
     *
     * @return array<mixed>
     */
    protected function getCoc(): array
    {
        if (is_string($this->request->input('buckaroo-billink-company-coc-registration'))) {
            return [
                'billing' => [
                    'recipient' => [
                        'chamberOfCommerce' => $this->request->input('buckaroo-billink-company-coc-registration'),
                    ],
                ],
            ];
        }

        return [];
    }

    /**
     * @return array<mixed>
     */
    protected function getBillingData(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();
        $country_code = $this->getAddress('billing', 'country');
        $first_name = $this->getAddress('billing', 'first_name');

        return [
            'billing' => [
                'recipient' => array_merge(
                    [
                        'category' => $this->getCategory('billing'),
                        'careOf' => $this->getCareOf('billing'),
                        'initials' => $this->getInitials($first_name),
                        'firstName' => $first_name,
                        'lastName' => $this->getAddress('billing', 'last_name'),
                        'title' => 'Unknown',
                    ],
                    $this->getBirthDate()
                ),
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->getAddress('billing', 'postcode'),
                    'city' => $this->getAddress('billing', 'city'),
                    'country' => $country_code,
                ],
                'phone' => [
                    'mobile' => $this->order_details->get_billing_phone(),
                ],
                'email' => $this->getAddress('billing', 'email'),
            ],
        ];
    }

    /**
     * Get type of request b2b or b2c
     */
    private function getCategory(string $address_type = 'billing'): string
    {
        if (! $this->isCompanyEmpty($this->getAddress($address_type, 'company'))) {
            return 'B2B';
        }

        return 'B2C';
    }

    /**
     * Check if company is empty
     */
    public function isCompanyEmpty(?string $company = null): bool
    {
        return $company === null || strlen(trim($company)) === 0;
    }

    /**
     * Get  careOf
     */
    private function getCareOf(string $address_type = 'billing'): string
    {
        $company = $this->getAddress($address_type, 'company');
        if (! $this->isCompanyEmpty()) {
            return $company;
        }

        return $this->order_details->get_full_name($address_type);
    }

    private function getInitials(string $name): string
    {
        return strtoupper(substr($name, 0, 1));
    }

    /**
     * Meta keys a date of birth is commonly stored under. WooCommerce has no
     * date of birth of its own, so this is whatever plugins put on the order or
     * on the customer.
     *
     * @var string[]
     */
    private const BIRTH_DATE_META_KEYS = [
        'billing_birthdate',
        '_billing_birthdate',
        'billing_date_of_birth',
        '_billing_date_of_birth',
        'billing_dob',
        '_billing_dob',
    ];

    /**
     * Birth date as a single-key array, so callers can merge it in and end up
     * with no birthDate at all when we have none. Billink One asks the customer
     * for it on its own page in that case.
     *
     * @return array<string, string>
     */
    private function getBirthDate(): array
    {
        $order = $this->get_order();

        $birthDate = apply_filters(
            'buckaroo_billink_birthdate',
            $this->findBirthDate($order),
            $order
        );

        if (! is_scalar($birthDate) || trim((string) $birthDate) === '') {
            return [];
        }

        $timestamp = strtotime((string) $birthDate);
        if ($timestamp === false) {
            return [];
        }

        return ['birthDate' => date('d-m-Y', $timestamp)];
    }

    /**
     * Look for a date of birth on the order first, then on the customer.
     *
     * @return null|string
     */
    private function findBirthDate(\WC_Order $order)
    {
        $customer_id = $order->get_customer_id();

        foreach (self::BIRTH_DATE_META_KEYS as $key) {
            $value = $order->get_meta($key, true);

            if (! is_scalar($value) || trim((string) $value) === '') {
                $value = $customer_id ? get_user_meta($customer_id, $key, true) : '';
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    protected function getShippingData(): array
    {
        $streetParts = $this->order_details->get_shipping_address_components();
        $country_code = $this->getAddress('shipping', 'country');
        $first_name = $this->getAddress('shipping', 'first_name');

        return [
            'shipping' => [
                'recipient' => array_merge(
                    [
                        'category' => $this->getCategory('shipping'),
                        'careOf' => $this->getCareOf('shipping'),
                        'initials' => $this->getInitials($first_name),
                        'firstName' => $first_name,
                        'lastName' => $this->getAddress('shipping', 'last_name'),
                    ],
                    $this->getBirthDate()
                ),
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
    }
}
