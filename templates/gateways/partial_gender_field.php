<?php
/**
 * The Template for displaying afterpay tos gateway template
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

$id = str_replace("_", "-", $this->id);
?>
<p class="form-row">
    <label for="<?php echo esc_attr($id)?>-gender">
        <?php echo esc_html_e('Gender:', 'wc-buckaroo-bpe-gateway') ?>
        <span class="required">*</span>
    </label>
    <input
    id="<?php echo esc_attr($id)?>-genderm"
    name="<?php echo esc_attr($id)?>-gender"
    class=""
    type="radio"
    value="1"
    checked
    />
    <label 
    for="<?php echo esc_attr($id)?>-genderm" 
    style="display:inline; margin-right:15px;">
        <?php echo esc_html_e('Male', 'wc-buckaroo-bpe-gateway') ?>
    </label>

    <input
    id="<?php echo esc_attr($id)?>-genderf"
    name="<?php echo esc_attr($id)?>-gender"
    class=""
    type="radio"
    value="2"
    />
    <label for="<?php echo esc_attr($id)?>-genderf" style="display:inline;">
        <?php echo esc_html_e('Female', 'wc-buckaroo-bpe-gateway') ?>
    </label>
</p>
