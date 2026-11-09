<?php

/**
 * The Template for displaying afterpaynew gateway template
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 2.25.0
 *
 * @link      https://www.buckaroo.eu/
 */

defined('ABSPATH') || exit;

// set customer phone
$customer_phone = $this->getScalarCheckoutField('billing_phone');

$country = $this->getScalarCheckoutField('billing_country');
$country = ! empty($country) ? $country : $this->country;

?>


<fieldset>
    <?php
    if ($country == 'FI') {
        woocommerce_form_field(
            'buckaroo-afterpaynew-identification-number',
            [
                'type' => 'text',
                'label' => __('Identification Number', 'wc-buckaroo-bpe-gateway'),
                'required' => true,
                'maxlength' => 250,
                'autocomplete' => 'off',
                'class' => ['form-row-wide'],
            ]
        );
    }

    if (in_array($country, ['BE', 'NL', 'DE'])) {
        $this->getPaymentTemplate('partial_birth_field');

        if (strlen(trim($customer_phone)) === 0) {
            woocommerce_form_field(
                'buckaroo-afterpaynew-phone',
                [
                    'type' => 'tel',
                    'label' => __('Phone:', 'wc-buckaroo-bpe-gateway'),
                    'required' => true,
                    'autocomplete' => 'off',
                    'class' => ['form-row-wide'],
                ]
            );
        }
    }

    if ($country == 'NL' && $this->customer_type !== \Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewGateway::CUSTOMER_TYPE_B2C) {
        woocommerce_form_field(
            'buckaroo-afterpaynew-company-coc-registration',
            [
                'type' => 'text',
                'label' => __('CoC-number:', 'wc-buckaroo-bpe-gateway'),
                'required' => true,
                'maxlength' => 250,
                'autocomplete' => 'off',
                'class' => ['form-row-wide'],
            ]
        );
    }
    ?>

    <?php if (! empty($this->getScalarCheckoutField('ship_to_different_address'))) { ?>
    <input
    id="buckaroo-afterpaynew-shipping-differ"
    name="buckaroo-afterpaynew-shipping-differ"
    class=""
    type="hidden"
    value="1" />
        <?php
    }
    $this->getPaymentTemplate('partial_afterpay_tos');
    ?>

</fieldset>
