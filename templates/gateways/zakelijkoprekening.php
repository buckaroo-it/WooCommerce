<?php

/**
 * The Template for displaying the Zakelijk op rekening gateway fields.
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @link      https://www.buckaroo.eu/
 */

defined('ABSPATH') || exit;

$company = $this->getScalarCheckoutField('billing_company');
$phone = $this->getScalarCheckoutField('billing_phone');
?>
<fieldset id="buckaroo_zakelijkoprekening_b2b">
    <p class="form-row form-row-wide">
        <?php esc_html_e('Voor iedereen, powered by ABN AMRO. Betaal later.', 'wc-buckaroo-bpe-gateway'); ?>
    </p>

    <?php
    if (strlen(trim($company)) === 0) {
        woocommerce_form_field(
            'buckaroo-zakelijkoprekening-company',
            [
                'type' => 'text',
                'label' => __('Company name:', 'wc-buckaroo-bpe-gateway'),
                'required' => true,
                'maxlength' => 250,
                'autocomplete' => 'organization',
                'class' => ['form-row-wide'],
            ]
        );
    }

    if (strlen(trim($phone)) === 0) {
        woocommerce_form_field(
            'buckaroo-zakelijkoprekening-phone',
            [
                'type' => 'tel',
                'label' => __('Phone:', 'wc-buckaroo-bpe-gateway'),
                'required' => true,
                'autocomplete' => 'tel',
                'class' => ['form-row-wide'],
            ]
        );
    }

    woocommerce_form_field(
        'buckaroo-zakelijkoprekening-company-coc-registration',
        [
            'type' => 'text',
            'label' => __('Chamber of Commerce (KvK) number:', 'wc-buckaroo-bpe-gateway'),
            'placeholder' => '12345678',
            'required' => true,
            'maxlength' => 14,
            'autocomplete' => 'off',
            'class' => ['form-row-wide'],
        ]
    );
    ?>
</fieldset>
