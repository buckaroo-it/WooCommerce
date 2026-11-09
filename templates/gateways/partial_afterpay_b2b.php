<?php

/**
 * The Template for displaying afterpay b2b gateway template
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

woocommerce_form_field(
    'buckaroo-afterpay-b2b',
    [
        'type' => 'checkbox',
        'label' => __('Checkout for company', 'wc-buckaroo-bpe-gateway'),
        'checked_value' => 'ON',
        'class' => ['form-row-wide'],
        'label_class' => ['woocommerce-form__label', 'woocommerce-form__label-for-checkbox'],
    ]
);
?>

<div id="showB2BBuckaroo" style="display:none">
    <p class="form-row form-row-wide">
        <?php esc_html_e('Fill required fields if bill in on the company:', 'wc-buckaroo-bpe-gateway'); ?>
    </p>
    <?php
    woocommerce_form_field(
        'buckaroo-afterpay-company-coc-registration',
        [
            'type' => 'text',
            'label' => __('COC (KvK) number:', 'wc-buckaroo-bpe-gateway'),
            'required' => true,
            'maxlength' => 250,
            'autocomplete' => 'off',
            'class' => ['form-row-wide'],
        ]
    );

    woocommerce_form_field(
        'buckaroo-afterpay-company-name',
        [
            'type' => 'text',
            'label' => __('Name of the organization:', 'wc-buckaroo-bpe-gateway'),
            'required' => true,
            'maxlength' => 250,
            'autocomplete' => 'off',
            'class' => ['form-row-wide'],
        ]
    );
    ?>
</div>
