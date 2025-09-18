<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;

class AfterpayNewProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    public function getAction(): string
    {
        if ($this->isAuthorization()) {
            if (get_post_meta($this->get_order()->get_id(), '_wc_order_authorized', true) == 'yes') {
                return 'capture';
            }

            return 'authorize';
        }

        return parent::getAction();
    }

    private function isAuthorization(): bool
    {
        return $this->gateway->get_option('afterpaynewpayauthorize', 'pay') === 'authorize';
    }

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        return array_merge_recursive(
            $this->getBillingData(),
            $this->getShippingData(),
            ['articles' => $this->getArticles()]
        );
    }

    /**
     * @return array<mixed>
     */
    protected function getBillingData(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();
        $country_code = $this->getAddress('billing', 'country');
        $data = [
            'billing' => [
                'recipient' => [
                    'category' => $this->getCategory('billing'),
                    'careOf' => $this->getCareOf('billing'),
                    'firstName' => $this->getAddress('billing', 'first_name'),
                    'lastName' => $this->getAddress('billing', 'last_name'),
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
                'email' => $this->getAddress('billing', 'email'),
            ],
        ];

        return array_merge_recursive(
            $data,
            $this->getCompany(),
            $this->getBirthDate($country_code)
        );
    }

    /**
     * Get type of request b2b or b2c
     */
    private function getCategory(string $address_type = 'billing'): string
    {
        if (
            $this->isB2b() &&
            $this->getAddress($address_type, 'country') === 'NL' &&
            ! $this->isCompanyEmpty($this->getAddress($address_type, 'company'))
        ) {
            return 'Company';
        }

        return 'Person';
    }

    public function isB2b(): bool
    {
        return $this->gateway->get_option('customer_type') !== AfterpayNewGateway::CUSTOMER_TYPE_B2C;
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

    private function getPhone(string $phone): string
    {
        return $this->request->input('buckaroo-afterpaynew-phone', $phone);
    }

    /**
     * @return array<mixed>
     */
    protected function getCompany(string $address_type = 'billing'): array
    {
        $company = $this->getAddress($address_type, 'company');
        if (
            $this->isB2b() &&
            $this->getAddress($address_type, 'country') === 'NL' &&
            ! $this->isCompanyEmpty($company)
        ) {
            return [
                $address_type => [
                    'recipient' => [
                        'companyName' => $company,
                        'chamberOfCommerce' => $this->request->input('buckaroo-afterpaynew-company-coc-registration'),
                    ],
                ],
            ];
        }

        return [];
    }

    /**
     * @return array<mixed>
     */
    protected function getBirthDate(string $country_code, string $type = 'billing'): array
    {
        if (in_array($country_code, ['NL', 'BE']) && ($birthDate = $this->getFormatedDate())) {
            add_post_meta($this->get_order()->get_id(), '_payload_birthday', $birthDate, true);

            return [
                $type => [
                    'recipient' => [
                        'birthDate' => $birthDate,
                    ],
                ],
            ];
        }

        return [];
    }

    /**
     * Get birth date
     */
    private function getFormatedDate(): ?string
    {
        $dateString = $this->request->input('buckaroo-afterpaynew-birthdate') ?: get_post_meta($this->get_order()->get_id(), '_payload_birthday', true);
        if (! is_scalar($dateString)) {
            return null;
        }
        $date = strtotime((string) $dateString);
        if ($date === false) {
            return null;
        }

        return @date('Y-m-d', $date);
    }

    protected function getShippingData(): array
    {
        $streetParts = $this->order_details->get_shipping_address_components();
        $country_code = $this->getAddress('shipping', 'country');

        $data = [
            'shipping' => [
                'recipient' => [
                    'category' => $this->getCategory('shipping'),
                    'careOf' => $this->getCareOf('shipping'),
                    'firstName' => $this->getAddress('shipping', 'first_name'),
                    'lastName' => $this->getAddress('shipping', 'last_name'),
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
            $this->getCompany('shipping'),
            $this->getBirthDate($country_code, 'shipping')
        );
    }

    public function unsuccessfulReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        if ($responseParser->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_REJECTED) {
            $errorMessage = $responseParser->getSubCodeMessage() ?: $responseParser->getServiceParameter('ErrorResponseMessage', 'afterpay');
            wc_add_notice(__($errorMessage, 'wc-buckaroo-bpe-gateway'), 'error');

            return [
                'redirect' => $redirectUrl . '?bck_err=' . $errorMessage,
                'result' => 'failure',
            ];
        }
    }
}
