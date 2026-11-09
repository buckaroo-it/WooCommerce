<?php

/**
 * The Template for displaying payperemail gateway template
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

$customerFirstName = $this->getScalarCheckoutField('billing_first_name');
$customerLastName = $this->getScalarCheckoutField('billing_last_name');
$customerEmail = $this->getScalarCheckoutField('billing_email');

?>
<fieldset>

<?php
$this->getPaymentTemplate('partial_gender_field');

woocommerce_form_field(
    'buckaroo-payperemail-firstname',
    [
        'type' => 'text',
        'label' => __('First Name:', 'wc-buckaroo-bpe-gateway'),
        'required' => true,
        'autocomplete' => 'off',
        'class' => ['form-row-wide'],
    ],
    $customerFirstName
);

woocommerce_form_field(
    'buckaroo-payperemail-lastname',
    [
        'type' => 'text',
        'label' => __('Last Name:', 'wc-buckaroo-bpe-gateway'),
        'required' => true,
        'autocomplete' => 'off',
        'class' => ['form-row-wide'],
    ],
    $customerLastName
);

woocommerce_form_field(
    'buckaroo-payperemail-email',
    [
        'type' => 'email',
        'label' => __('Email:', 'wc-buckaroo-bpe-gateway'),
        'required' => true,
        'autocomplete' => 'off',
        'class' => ['form-row-wide'],
    ],
    $customerEmail
);
?>
</fieldset>
