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

?>
<fieldset id="buckaroo_zakelijkoprekening_b2b">
    <p class="form-row form-row-wide">
        <?php esc_html_e('Voor iedereen, powered by ABN AMRO. Betaal later.', 'wc-buckaroo-bpe-gateway'); ?>
    </p>

    <p class="form-row form-row-wide">
        <?php esc_html_e('Available for companies in The Netherlands. Make sure a company name is filled in your billing details.', 'wc-buckaroo-bpe-gateway'); ?>
    </p>

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
