<?php

/**
 * The Template for displaying afterpay tos gateway template
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

$section_id = str_replace('_', '-', $this->id);

woocommerce_form_field(
    $section_id . '-birthdate',
    [
        'type' => 'text',
        'label' => __('Birthdate (format DD-MM-YYYY):', 'wc-buckaroo-bpe-gateway'),
        'placeholder' => 'DD-MM-YYYY',
        'required' => true,
        'maxlength' => 250,
        'autocomplete' => 'off',
        'class' => ['form-row-wide'],
    ]
);
