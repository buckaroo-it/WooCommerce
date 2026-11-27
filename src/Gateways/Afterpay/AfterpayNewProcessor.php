<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\CheckoutError;
use Buckaroo\Woocommerce\Traits\HasDateValidation;
use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;

class AfterpayNewProcessor extends AbstractPaymentProcessor
{
    use HasDateValidation;

    /** {@inheritDoc} */
    public function getAction(): string
    {
        if ($this->isAuthorization()) {
            if (OrderMeta::get($this->get_order(), '_wc_order_authorized') == 'yes') {
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

    /**
     * Riverty rejects a capture whose invoice number was already used for the
     * order (BTI-2181). Payment, authorization and the first capture send the
     * order number, which merchants reconcile on (BP-4404); each later capture
     * adds its position: 444, 444-2, 444-3. CaptureAction reloads the recorded
     * captures under the order's capture lock before the body is built.
     */
    protected function get_invoice_number(): string
    {
        $orderNumber = parent::get_invoice_number();
        if ($this->getAction() !== 'capture') {
            return $orderNumber;
        }

        $captureCount = count(OrderMeta::get($this->get_order(), '_wc_order_captures', false));

        return $captureCount > 0 ? $orderNumber . '-' . ($captureCount + 1) : $orderNumber;
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
            $this->getBirthDate($country_code),
            $this->getIdentificationNumber($country_code)
        );
    }

    /**
     * Riverty needs the personal identification number of a Finnish shopper.
     * Left out when blank, as the capture reuses this body (BTI-1880).
     *
     * @return array<mixed>
     */
    protected function getIdentificationNumber(string $country_code): array
    {
        $identificationNumber = $this->request->input('buckaroo-afterpaynew-identification-number');
        if ($country_code !== 'FI' || ! is_string($identificationNumber) || trim($identificationNumber) === '') {
            return [];
        }

        return [
            'billing' => [
                'recipient' => [
                    'identificationNumber' => trim($identificationNumber),
                ],
            ],
        ];
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
     * The company for a request sent as a company, otherwise the person's
     * name, so careOf always matches the category (BTI-1880).
     */
    private function getCareOf(string $address_type = 'billing'): string
    {
        if ($this->getCategory($address_type) === 'Company') {
            return $this->getAddress($address_type, 'company');
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
        $date = $this->parseBirthdate($this->request->input('buckaroo-afterpaynew-birthdate'));

        return $date !== null ? $date->format('Y-m-d') : null;
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
            wc_add_notice($errorMessage, 'error');

            return [
                'redirect' => CheckoutError::addToUrl($redirectUrl),
                'result' => 'failure',
            ];
        }
    }
}
