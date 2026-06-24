<?php

/**
 * The Template for displaying the Zakelijk op rekening gateway fields.
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @link      https://www.buckaroo.eu/
 */

defined('ABSPATH') || exit;

$company = $this->getScalarCheckoutField('billing_company');
?>
<fieldset id="buckaroo_zakelijkoprekening_b2b">
    <p class="form-row form-row-wide">
        <?php esc_html_e('Voor iedereen, powered by ABN AMRO. Betaal later.', 'wc-buckaroo-bpe-gateway'); ?>
    </p>

    <?php if (strlen(trim($company)) === 0) { ?>
    <p class="form-row form-row-wide validate-required">
        <label for="buckaroo-zakelijkoprekening-company">
            <?php esc_html_e('Company name:', 'wc-buckaroo-bpe-gateway'); ?>
            <span class="required">*</span>
        </label>
        <input
            id="buckaroo-zakelijkoprekening-company"
            name="buckaroo-zakelijkoprekening-company"
            class="input-text"
            type="text"
            maxlength="250"
            autocomplete="organization"
            value="" />
    </p>
    <?php } ?>

    <p class="form-row form-row-wide validate-required">
        <label for="buckaroo-zakelijkoprekening-company-coc-registration">
            <?php esc_html_e('Chamber of Commerce (KvK) number:', 'wc-buckaroo-bpe-gateway'); ?>
            <span class="required">*</span>
        </label>
        <input
            id="buckaroo-zakelijkoprekening-company-coc-registration"
            name="buckaroo-zakelijkoprekening-company-coc-registration"
            class="input-text"
            type="text"
            maxlength="8"
            autocomplete="off"
            value="" />
    </p>

    <p class="required" style="float:right;">
        * <?php esc_html_e('Required', 'wc-buckaroo-bpe-gateway'); ?>
    </p>
</fieldset>
