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

use Buckaroo\Woocommerce\Services\Helper;

defined('ABSPATH') || exit;

$section_id = str_replace('_', '-', $this->id);

$allGenders = Helper::getAllGendersForPaymentMethods();
$genderVal = $allGenders[$section_id] ?? [];

$genderOptions = [];
foreach ($genderVal as $key => $value) {
    $genderOptions[$value] = Helper::translateGender($key);
}

woocommerce_form_field(
    $section_id . '-gender',
    [
        'type' => 'select',
        'label' => __('Gender:', 'wc-buckaroo-bpe-gateway'),
        'required' => true,
        'options' => $genderOptions,
        'class' => ['form-row-wide'],
    ]
);
