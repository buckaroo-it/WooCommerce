<?php

/**
 * The Template for displaying bilink gateway template
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
?>


<fieldset id="buckaroo_billink_b2b">
    <p class="form-row form-row-wide">
        <?php esc_html_e('Fill required fields if bill in on the company:', 'wc-buckaroo-bpe-gateway'); ?>
    </p>
    <?php
    woocommerce_form_field(
        'buckaroo-billink-company-coc-registration',
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
        'buckaroo-billink-VatNumber',
        [
            'type' => 'text',
            'label' => __('VAT number:', 'wc-buckaroo-bpe-gateway'),
            'maxlength' => 250,
            'autocomplete' => 'off',
            'class' => ['form-row-wide'],
        ]
    );
    ?>
</fieldset>

<?php if (! empty($this->getScalarCheckoutField('ship_to_different_address'))) { ?>
    <input
    id="buckaroo-billink-shipping-differ"
    name="buckaroo-billink-shipping-differ"
    class=""
    type="hidden"
    value="1" />
    <?php
}
