<?php
/**
 * The Template for displaying creditcard gateway template
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */

defined( 'ABSPATH' ) || exit;

$creditCardMethod = isset( $this->creditcardmethod ) ? $this->creditcardmethod : 'redirect';
$customer_name    = implode( ' ', array( $this->getScalarCheckoutField( 'billing_first_name' ), $this->getScalarCheckoutField( 'billing_last_name' ) ) );
?>

<fieldset class="buckaroo-creditcard-fieldset">
    <input
            type="hidden"
            name="<?php echo esc_attr( $this->id ); ?>-creditcard-issuer"
            value="<?php echo esc_attr( str_replace( 'buckaroo_creditcard_', '', $this->id ) ); ?>"
    />

    <?php if ( $creditCardMethod == 'encrypt' && $this->isSecure() ) : ?>
        <div class="<?php echo esc_attr( $this->id ); ?>-hf-error woocommerce-error"></div>

        <div class="form-row form-row-wide validate-required">
            <label id="<?php echo esc_attr( $this->id ); ?>-name-label" class="buckaroo-label">
                <?php esc_html_e( 'Cardholder Name:', 'wc-buckaroo-bpe-gateway' ); ?>
                <span class="required">*</span>
            </label>
            <div id="<?php echo esc_attr( $this->id ); ?>-name-wrapper" class="cardHolderName input-text"></div>
            <div id="<?php echo esc_attr( $this->id ); ?>-name-error" class="input-error"></div>
        </div>

        <div class="form-row form-row-wide validate-required">
            <label id="<?php echo esc_attr( $this->id ); ?>-number-label" class="buckaroo-label">
                <?php esc_html_e( 'Card Number:', 'wc-buckaroo-bpe-gateway' ); ?>
                <span class="required">*</span>
            </label>
            <div id="<?php echo esc_attr( $this->id ); ?>-number-wrapper" class="cardNumber input-text"></div>
            <div id="<?php echo esc_attr( $this->id ); ?>-number-error" class="input-error"></div>
        </div>

        <div class="form-row form-row-first">
            <label id="<?php echo esc_attr( $this->id ); ?>-expiry-label" class="buckaroo-label">
                <?php esc_html_e( 'Expiration Date:', 'wc-buckaroo-bpe-gateway' ); ?>
                <span class="required">*</span>
            </label>
            <div id="<?php echo esc_attr( $this->id ); ?>-expiry-wrapper" class="expirationDate input-text"></div>
            <div id="<?php echo esc_attr( $this->id ); ?>-expiry-error" class="input-error"></div>
        </div>

        <div class="form-row form-row-last">
            <label id="<?php echo esc_attr( $this->id ); ?>-cvc-label" class="buckaroo-label">
                <?php esc_html_e( 'CVC:', 'wc-buckaroo-bpe-gateway' ); ?>
                <span class="required">*</span>
            </label>
            <div id="<?php echo esc_attr( $this->id ); ?>-cvc-wrapper" class="cvc input-text"></div>
            <div id="<?php echo esc_attr( $this->id ); ?>-cvc-error" class="input-error"></div>
        </div>

        <input
                type="hidden"
                id="<?php echo esc_attr( $this->id ); ?>-encrypted-data"
                name="<?php echo esc_attr( $this->id ); ?>-encrypted-data"
                class="encryptedCardData input-text">
    <?php endif; ?>


    <p class="form-row form-row-wide validate-required"></p>
    <p class="required" style="float:right;">*
        <?php echo esc_html_e( 'Required', 'wc-buckaroo-bpe-gateway' ); ?>
    </p>
</fieldset>
