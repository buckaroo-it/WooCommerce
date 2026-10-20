<?php

/**
 * The Template for displaying afterpay gateway template
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

?>

<fieldset>
    <?php

    if ($this->b2b == 'enable' && $this->type == 'afterpaydigiaccept') {
        $this->getPaymentTemplate('partial_afterpay_b2b');
    }
    $this->getPaymentTemplate('partial_birth_field');

    if (strlen(trim($customer_phone)) === 0) {
        woocommerce_form_field(
            'buckaroo-afterpay-phone',
            [
                'type' => 'tel',
                'label' => __('Phone:', 'wc-buckaroo-bpe-gateway'),
                'required' => true,
                'autocomplete' => 'off',
                'class' => ['form-row-wide'],
            ]
        );
    }
    ?>
    <?php
    if (! empty($this->getScalarCheckoutField('ship_to_different_address'))) {
        ?>
        <input id="buckaroo-afterpay-shipping-differ"
        name="buckaroo-afterpay-shipping-differ"
        class=""
        type="hidden"
        value="1" />
        <?php
    }
    ?>
    <?php
    if ($this->type == 'afterpayacceptgiro') {
        woocommerce_form_field(
            'buckaroo-afterpay-company-coc-registration',
            [
                'type' => 'text',
                'label' => __('IBAN:', 'wc-buckaroo-bpe-gateway'),
                'required' => true,
                'class' => ['form-row-wide'],
            ]
        );
    }
    $this->getPaymentTemplate('partial_afterpay_tos');
    ?>
</fieldset>
