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
    ?>

    <p class="form-row validate-required">
        <label for="<?php echo esc_attr($this->getKlarnaSelector()); ?>-phone">
            <?php esc_html_e('Phone:', 'wc-buckaroo-bpe-gateway'); ?>
            <span class="required">*</span>
        </label>
        <input id="<?php echo esc_attr($this->getKlarnaSelector()); ?>-phone"
        name="<?php echo esc_attr($this->getKlarnaSelector()); ?>-phone"
        class="input-tel"
        type="tel"
        autocomplete="off"
        value="<?php echo esc_html($customerPhone) ?? ''; ?>">
    </p>

    <?php if (! empty($this->getScalarCheckoutField('ship_to_different_address'))) { ?>
    <input
    id="<?php echo esc_attr($this->getKlarnaSelector()); ?>-shipping-differ"
    name="<?php echo esc_attr($this->getKlarnaSelector()); ?>-shipping-differ"
    class=""
    type="hidden"
    value="1" />
    <?php } ?>

    <p class="required" style="float:right;">*
        <?php esc_html_e('Required', 'wc-buckaroo-bpe-gateway'); ?>
    </p>
</fieldset>
