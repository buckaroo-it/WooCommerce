<?php
/**
 * The Template for displaying afterpay gateway template
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


//set customer phone
$customer_phone = $this->request_scalar('billing_phone');


?>

<fieldset>
    <?php

    if ($this->b2b == 'enable' && $this->type == 'afterpaydigiaccept') {
        $this->get_template('partial_afterpay_b2b');
    }
    $this->get_template('partial_birth_field');
    ?>
    <p class="form-row validate-required">
        <label for="buckaroo-afterpay-phone">
            <?php echo esc_html_e('Phone:', 'wc-buckaroo-bpe-gateway') ?>
            <span class="required">*</span>
        </label>

        <input id="buckaroo-afterpay-phone"
        name="buckaroo-afterpay-phone"
        class="input-tel"
        type="tel"
        autocomplete="off"
        value="<?php echo esc_html($customer_phone) ?? '' ?>">
    </p>
    <?php 
    if (!empty($this->request_scalar('ship_to_different_address'))) {
        ?>
        <input id="buckaroo-afterpay-shipping-differ"
        name="buckaroo-afterpay-shipping-differ"
        class=""
        type="hidden"
        value="1" />
        <?php
    } ?>
    <?php if ($this->type == 'afterpayacceptgiro') {
        ?>
        <p class="form-row form-row-wide validate-required">
            <label for="buckaroo-afterpay-company-coc-registration">
                <?php echo esc_html_e('IBAN:', 'wc-buckaroo-bpe-gateway') ?>
                <span class="required">*</span>
            </label>
            
            <input 
            id="buckaroo-afterpay-company-coc-registration"
            name="buckaroo-afterpay-company-coc-registration"
            class="input-text"
            type="text"
            value="" />
        </p>
        <?php
    }
    $this->get_template('partial_afterpay_tos');
    $this->get_template('financial_warning');
    ?>
</fieldset>