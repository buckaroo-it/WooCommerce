<?php

namespace WC_Buckaroo\WooCommerce\Payment\Methods;
class Buckaroo_Paypal extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        if ($this->is_express()) {
            return ['payPalOrderId' => $this->get_express_id()];
        }

        if ($this->is_seller_protection()) {
            return $this->get_seller_data();
        }
        return [];
    }

    /** @inheritDoc */
    public function get_action(): string
    {
        if ($this->is_seller_protection() && !$this->is_express()) {
            return 'extraInfo';
        }
        return 'pay';
    }

    private function is_express(): bool
    {
        return is_string($this->get_express_id());
    }

    private function get_express_id(): ?string
    {
        if (method_exists($this->gateway, "get_express_order_id")) {
            return $this->gateway->get_express_order_id();
        }
        return null;
    }

    /**
     * Get seller protection data
     *
     * @return array
     */
    private function get_seller_data(): array
    {
        return [
            'customer' => [
                'name' => $this->order_details->get_full_name(),
            ],
            'address' => [
                'street' => $this->get_address('billing', 'address_1'),
                'zipcode' => $this->get_address('billing', 'postcode'),
                'city' => $this->get_address('billing', 'city'),
                'state' => $this->get_address('billing', 'state'),
                'country' => $this->get_address('billing', 'country')
            ],
            'phone' => [
                'mobile' => $this->get_address('billing', 'phone')
            ]
        ];
    }

    /**
     * Check if seller protection is enabled
     *
     * @return bool
     */
    private function is_seller_protection(): bool
    {
        return $this->gateway->get_option('sellerprotection', 'TRUE') === 'TRUE';
    }
}
