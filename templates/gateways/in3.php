<?php

/**
 * The Template for displaying in3 gateway template
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

$country = $this->getScalarCheckoutField('billing_country');
$country = ! empty($country) ? $country : $this->country;
$customer_phone = $this->getScalarCheckoutField('billing_phone');

?>
<fieldset>
    <?php
    if ($country == 'NL') {
        $this->getPaymentTemplate('partial_birth_field');
    }

    if (strlen(trim($customer_phone)) === 0) {
        woocommerce_form_field(
            'buckaroo-in3-phone',
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
</fieldset>
