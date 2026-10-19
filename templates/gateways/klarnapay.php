<?php

/**
 * The Template for displaying karnapay gateway template
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

$customerPhone = $this->getScalarCheckoutField('billing_phone');
$country = $this->getScalarCheckoutField('billing_country');

?>

<fieldset>
    <?php
    // Gender selection removed from checkout to reduce friction; the gateway
    // always sends "Unknown" for the mandatory Klarna gender/salutation parameter.

    woocommerce_form_field(
        $this->getKlarnaSelector() . '-phone',
        [
            'type' => 'tel',
            'label' => __('Phone:', 'wc-buckaroo-bpe-gateway'),
            'required' => true,
            'autocomplete' => 'off',
            'class' => ['form-row-wide'],
        ],
        $customerPhone
    );
    ?>

    <?php if (! empty($this->getScalarCheckoutField('ship_to_different_address'))) { ?>
    <input
    id="<?php echo esc_attr($this->getKlarnaSelector()); ?>-shipping-differ"
    name="<?php echo esc_attr($this->getKlarnaSelector()); ?>-shipping-differ"
    class=""
    type="hidden"
    value="1" />
    <?php } ?>
</fieldset>
