<?php

/**
 * The Template for displaying paybybank gateway template
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
?>
<fieldset style="background: none">
    <?php if ($this->get_option('displaymode') === 'dropdown') { ?>
        <p class="form-row form-row-wide">
            <select name="buckaroo-paybybank-issuer" id="buckaroo-paybybank-issuer">
                <option value="0" style="color: grey !important">
                    <?php echo esc_html_e("Select your bank", "wc-buckaroo-bpe-gateway")?>
                </option>
                <?php foreach (BuckarooPayByBank::getIssuerList() as $key => $issuer) : ?>
                <div>
                    <option value="<?php echo esc_attr($key); ?>" <?php if(isset($issuer["selected"]) && $issuer["selected"] === true) {?> selected <?php } ?> id="bankMethod<?php echo esc_attr($key); ?>">
                        <?php echo esc_html_e($issuer["name"], "wc-buckaroo-bpe-gateway")?>
                    </option>
                </div>
                <?php endforeach ?>
            </select>
        </p>
    <?php } else { ?>
    <div class="form-row form-row-wide bk-paybybank-selector">
        <?php foreach (BuckarooPayByBank::getIssuerList() as $key => $issuer) : ?>
            <div class="custom-control custom-radio bank-control">
                <input type="radio" <?php if(isset($issuer["selected"]) && $issuer["selected"] === true) {?> checked <?php } ?> id="bankMethod<?php echo esc_attr($key); ?>" name="buckaroo-paybybank-issuer" value="<?php echo esc_attr($key); ?>" class="custom-control-input bank-method-input">
                <label class="custom-control-label bank-method-label" for="bankMethod<?php echo esc_attr($key); ?>">
                    <img src="<?php echo esc_url(plugin_dir_url(__DIR__) . "../library/buckaroo_images/ideal/" . $issuer['logo']); ?>" wdith="45" class="bank-method-image" alt="<?php echo esc_html_e($issuer["name"], "wc-buckaroo-bpe-gateway") ?>" title="<?php echo esc_html_e($issuer["name"], "wc-buckaroo-bpe-gateway") ?>">
                    <strong><?php echo esc_html_e($issuer["name"], "wc-buckaroo-bpe-gateway") ?></strong>
                </label>
            </div>
        <?php endforeach ?>
    </div>
    <div class="bk-paybybank-toggle-list">
        <div class="bk-toggle-wrap">
            <div class="bk-toggle-text" text-less="<?php echo esc_html_e('Less banks', "wc-buckaroo-bpe-gateway") ?>" text-more="<?php echo esc_html_e('More banks', "wc-buckaroo-bpe-gateway") ?>">
                <?php echo esc_html_e('More banks', "wc-buckaroo-bpe-gateway") ?>
            </div>
            <div class="bk-toggle bk-toggle-down"></div>
        </div>
    </div>
</fieldset>

<?php } ?>