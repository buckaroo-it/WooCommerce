<?php

class Buckaroo_PayPerEmail extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return [
            'email' => $this->request_string(
                'buckaroo-payperemail-email',
                $this->get_address('billing', 'email'),
            ),
            'customer'  => [
                'firstName'     =>  $this->request_string(
                    'buckaroo-payperemail-firstname',
                    $this->get_address('billing', 'first_name'),
                ),
                'lastName'      => $this->request_string(
                    'buckaroo-payperemail-lastname',
                    $this->get_address('billing', 'last_name'),
                ),
                'gender' => $this->request_string('buckaroo-payperemail-gender'),

            ],
            'expirationDate'        => $this->get_expiration_date(),
            'paymentMethodsAllowed' => $this->get_allowed_methods()
        ];
    }

    /** @inheritDoc */
    public function get_action(): string
    {
        return 'paymentInvitation';
    }

    private function get_allowed_methods(): string
    {
        $methods = $this->gateway->get_option('paymentmethodppe');
        if (is_array($methods)) {
            return implode(",", $methods);
        }
        return '';
    }

    private function get_expiration_date(): string
    {
        $payperemailExpireDays = $this->gateway->get_option('expirationDate');

        if (!is_scalar($payperemailExpireDays)) {
            return '';
        }

        return date('Y-m-d', time() + (int)$payperemailExpireDays * 86400);
    }
}
