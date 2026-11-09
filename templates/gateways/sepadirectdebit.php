<?php

/**
 * The Template for displaying sepadirect gateway template
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

$customer_name = $this->getScalarCheckoutField('billing_first_name');
$customer_name .= ' ' . $this->getScalarCheckoutField('billing_last_name');

?>

<fieldset>
    <?php
    woocommerce_form_field(
        'buckaroo-sepadirectdebit-accountname',
        [
            'type' => 'text',
            'label' => __('Bank account holder:', 'wc-buckaroo-bpe-gateway'),
            'required' => true,
            'maxlength' => 250,
            'autocomplete' => 'off',
            'class' => ['form-row-wide'],
        ],
        $customer_name
    );

    woocommerce_form_field(
        'buckaroo-sepadirectdebit-iban',
        [
            'type' => 'text',
            'label' => __('IBAN:', 'wc-buckaroo-bpe-gateway'),
            'required' => true,
            'maxlength' => 25,
            'autocomplete' => 'off',
            'class' => ['form-row-wide'],
        ]
    );

    woocommerce_form_field(
        'buckaroo-sepadirectdebit-bic',
        [
            'type' => 'text',
            'label' => __('BIC:', 'wc-buckaroo-bpe-gateway'),
            'maxlength' => 11,
            'autocomplete' => 'off',
            'class' => ['form-row-wide'],
        ]
    );
    ?>
</fieldset>
