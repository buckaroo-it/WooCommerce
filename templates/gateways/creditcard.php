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

defined('ABSPATH') || exit;

$creditCardMethod = isset($this->creditcardmethod) ? $this->creditcardmethod : 'redirect';


$customerName = $this->geCheckoutField('billing_first_name');
$customerName.= " ".$this->geCheckoutField('billing_last_name');
?>


<fieldset>
    <div class="method--bankdata">
        <p class="form-row form-row-wide">
            <select
            name='buckaroo-creditcard-issuer'
            id='buckaroo-creditcard-issuer'>
                <option value='0' style='color: grey !important'>
                    <?php echo __('Select your credit card:', 'wc-buckaroo-bpe-gateway') ?>
                </option>
                <?php foreach ($this->getCardsList() as $issuer): ?>
                <div>
                    <option value='<?php echo $issuer['servicename']; ?>'>
                        <?php echo _e($issuer['displayname'], 'wc-buckaroo-bpe-gateway') ?>
                    </option>
                </div>
                <?php endforeach?>
            </select>
        </p>

        <?php if ($creditCardMethod == 'encrypt' && $this->isSecure()) : ?>

        <p class="form-row">
            <label class="buckaroo-label" for="buckaroo-creditcard-cardname">
                <?php echo _e('Cardholder Name:', 'wc-buckaroo-bpe-gateway') ?>
                <span class="required">*</span>
            </label>

            <input
            type="text"
            name="buckaroo-creditcard-cardname"
            id="buckaroo-creditcard-cardname"
            placeholder="<?php echo __('Cardholder Name:', 'wc-buckaroo-bpe-gateway') ?>"
            class="cardHolderName input-text"
            maxlength="250"
            autocomplete="off"
            value="<?php echo $customerName ?? '' ?>">
        </p>

        <p class="form-row">
            <label class="buckaroo-label" for="buckaroo-creditcard-cardnumber">
                <?php echo _e('Card Number:', 'wc-buckaroo-bpe-gateway') ?>
                <span class="required">*</span>
            </label>

            <input
            type="text"
            name="buckaroo-creditcard-cardnumber"
            id="buckaroo-creditcard-cardnumber"
            placeholder="<?php echo __('Card Number:', 'wc-buckaroo-bpe-gateway') ?>"
            class="cardNumber input-text"
            maxlength="250"
            autocomplete="off"
            value="">
        </p>

        <p class="form-row">
            <label class="buckaroo-label" for="buckaroo-creditcard-cardmonth">
                <?php echo _e('Expiration Month:', 'wc-buckaroo-bpe-gateway') ?>
                <span class="required">*</span>
            </label>

            <input
            type="text"
            maxlength="2"
            name="buckaroo-creditcard-cardmonth"
            id="buckaroo-creditcard-cardmonth"
            placeholder="<?php echo __('Expiration Month:', 'wc-buckaroo-bpe-gateway') ?>"
            class="expirationMonth input-text"
            maxlength="250"
            autocomplete="off"
            value="">
        </p>

        <p class="form-row">
            <label class="buckaroo-label" for="buckaroo-creditcard-cardyear">
                <?php echo _e('Expiration Year:', 'wc-buckaroo-bpe-gateway') ?>
                <span class="required">*</span>
            </label>
            <input
            type="text"
            maxlength="4"
            name="buckaroo-creditcard-cardyear"
            id="buckaroo-creditcard-cardyear"
            placeholder="<?php echo __('Expiration Year:', 'wc-buckaroo-bpe-gateway') ?>"
            class="expirationYear input-text"
            maxlength="250"
            autocomplete="off"
            value="">
        </p>

        <p class="form-row">
            <label class="buckaroo-label" for="buckaroo-creditcard-cardcvc">
                <?php echo _e('CVC:', 'wc-buckaroo-bpe-gateway') ?>
                <span class="required">*</span>
            </label>
            <input
            type="password"
            maxlength="4"
            name="buckaroo-creditcard-cardcvc"
            id="buckaroo-creditcard-cardcvc"
            placeholder="<?php echo __('CVC:', 'wc-buckaroo-bpe-gateway') ?>"
            class="cvc input-text"
            maxlength="250"
            autocomplete="off"
            value="">
        </p>

        <p class="form-row form-row-wide validate-required"></p>
        <p class="required" style="float:right;">*
            <?php echo _e('Obligatory fields', 'wc-buckaroo-bpe-gateway') ?>
        </p>

        <input
        type="hidden"
        id="buckaroo-encrypted-data"
        name="buckaroo-encrypted-data"
        class="encryptedCardData input-text">
        <?php endif;?>

    </div>
</fieldset>
