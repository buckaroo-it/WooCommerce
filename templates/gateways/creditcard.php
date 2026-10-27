<?php

/**
 * The Template for displaying creditcard gateway template
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

$creditCardMethod = isset($this->creditcardmethod) ? $this->creditcardmethod : 'redirect';
$customer_name = implode(' ', [$this->getScalarCheckoutField('billing_first_name'), $this->getScalarCheckoutField('billing_last_name')]);
$show_required = ($creditCardMethod == 'encrypt' && $this->isSecure()) || ($creditCardMethod == 'redirect' && $this->id === 'buckaroo_creditcard');

if (! $show_required) {
    ?>
    <input
        type="hidden"
        name="<?php echo esc_attr($this->id); ?>-creditcard-issuer"
        value="<?php echo esc_attr(str_replace('buckaroo_creditcard_', '', $this->id)); ?>"
    />
    <?php
    return;
}
?>

<fieldset class="buckaroo-creditcard-fieldset">
    <?php
    if ($creditCardMethod == 'redirect' && $this->id === 'buckaroo_creditcard') {
        // An empty value marks the placeholder, so WooCommerce's required check flags it.
        $cardOptions = ['' => __('Select your credit card:', 'wc-buckaroo-bpe-gateway')];
        foreach ($this->getCheckoutCardsList() as $issuer) {
            $cardOptions[$issuer['servicename']] = __($issuer['displayname'], 'wc-buckaroo-bpe-gateway');
        }

        woocommerce_form_field(
            $this->id . '-creditcard-issuer',
            [
                'type' => 'select',
                'id' => 'buckaroo-creditcard-issuer',
                'label' => __('Credit Card:', 'wc-buckaroo-bpe-gateway'),
                'required' => true,
                'options' => $cardOptions,
                'class' => ['form-row-wide'],
            ]
        );
        ?>
    <?php } else { ?>
        <input
            type="hidden"
            name="<?php echo esc_attr($this->id); ?>-creditcard-issuer"
            value="<?php echo esc_attr(str_replace('buckaroo_creditcard_', '', $this->id)); ?>"
        />
    <?php } ?>
    <?php if ($creditCardMethod == 'encrypt' && $this->isSecure()) { ?>
        <div class="<?php echo esc_attr($this->id); ?>-hf-error woocommerce-error"></div>

        <?php
        $hostedFields = [
            'name' => [__('Cardholder Name:', 'wc-buckaroo-bpe-gateway'), 'form-row-wide'],
            'number' => [__('Card Number:', 'wc-buckaroo-bpe-gateway'), 'form-row-wide'],
            'expiry' => [__('Expiration Date:', 'wc-buckaroo-bpe-gateway'), 'form-row-first'],
            'cvc' => [__('CVC:', 'wc-buckaroo-bpe-gateway'), 'form-row-last'],
        ];

        // WooCommerce's form row markup around an inert input that only draws the
        // field; the card iframe is mounted over it into the frame.
        foreach ($hostedFields as $field => [$label, $rowClass]) {
            $fieldId = $this->id . '-' . $field;
            ?>
            <p class="form-row <?php echo esc_attr($rowClass); ?> validate-required buckaroo-hosted-field-row">
                <label id="<?php echo esc_attr($fieldId); ?>-label">
                    <?php echo esc_html($label); ?>&nbsp;<span class="required" aria-hidden="true">*</span>
                </label>
                <span class="woocommerce-input-wrapper buckaroo-hosted-field">
                    <input type="text" class="input-text buckaroo-hosted-field__input" tabindex="-1" aria-hidden="true" readonly>
                    <span id="<?php echo esc_attr($fieldId); ?>-wrapper" class="buckaroo-hosted-field__frame"></span>
                </span>
                <span id="<?php echo esc_attr($fieldId); ?>-error" class="buckaroo-hosted-field__error" role="alert"></span>
            </p>
            <?php
        }
        ?>
        <div class="clear"></div>

        <input
                type="hidden"
                id="<?php echo esc_attr($this->id); ?>-encrypted-data"
                name="<?php echo esc_attr($this->id); ?>-encrypted-data"
                class="encryptedCardData input-text">
    <?php } ?>
</fieldset>
