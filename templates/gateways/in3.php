<?php
/**
 * The Template for displaying in3 gateway template
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

$country = $this->getScalarCheckoutField('billing_country');
$country = !empty($country) ? $country : $this->country;

?>
<fieldset>
    <?php if ($country == "NL") : 
        $this->getPaymentTemplate('partial_gender_field');
        $this->getPaymentTemplate('partial_birth_field');
        ?>
    <p class="form-row form-row-wide validate-required">
        <label for="buckaroo-in3-orderas">
            <?php echo esc_html_e('Order as:', 'wc-buckaroo-bpe-gateway') ?>
            <span class="required">*</span>
        </label>
        <select
        id="buckaroo-in3-orderas"
        name="buckaroo-in3-orderas"
        class=""
        >
            <option value="Debtor">
                <?php echo esc_html_e('Debtor', 'wc-buckaroo-bpe-gateway') ?>
            </option>
            <option value="SoleProprietor">
                <?php echo esc_html_e('SoleProprietor', 'wc-buckaroo-bpe-gateway') ?>
            </option>
            <option value="Company">
                <?php echo esc_html_e('Company', 'wc-buckaroo-bpe-gateway') ?>
            </option>
        </select>
    </p>

    <p
    class="form-row form-row-wide validate-required"
    id="buckaroo-in3-coc-container"
    style="display: none">
        <label for="buckaroo-in3-coc">
            <?php echo esc_html_e('COC Number:', 'wc-buckaroo-bpe-gateway') ?>
            <span class="required">*</span>
        </label>
        <input
        id="buckaroo-in3-coc"
        name="buckaroo-in3-coc"
        class=""
        maxlength="250" />
    </p>

    <p
    class="form-row form-row-wide validate-required"
    id="buckaroo-in3-companyname-container"
    style="display: none">
        <label for="buckaroo-in3-companyname">
            <?php echo esc_html_e('Company Name:', 'wc-buckaroo-bpe-gateway') ?>
            <span class="required">*</span>
        </label>
        <input
        id="buckaroo-in3-companyname"
        name="buckaroo-in3-companyname"
        class=""
        maxlength="250" />
    </p>
    <p class="required" style="float:right;">
        * <?php echo esc_html_e('Required', 'wc-buckaroo-bpe-gateway') ?>
    </p>
    <?php endif;?>
</fieldset>