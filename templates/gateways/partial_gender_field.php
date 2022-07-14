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

$section_id = str_replace("_", "-", $this->id);
?>
<p class="form-row">
    <label for="<?php echo esc_attr($section_id)?>-gender">
        <?php echo esc_html_e('Gender:', 'wc-buckaroo-bpe-gateway') ?>
        <span class="required">*</span>
    </label>
    <select name="<?php echo esc_attr($section_id)?>-gender" id="<?php echo esc_attr($section_id)?>-gender">
        <option value="1"><?php echo esc_html_e('He/him', 'wc-buckaroo-bpe-gateway') ?></option>
        <option value="2"><?php echo esc_html_e('She/her', 'wc-buckaroo-bpe-gateway') ?></option>
        <option value="0"><?php echo esc_html_e('They/them', 'wc-buckaroo-bpe-gateway') ?></option>
        <option value="9"><?php echo esc_html_e('I prefer not to say', 'wc-buckaroo-bpe-gateway') ?></option>
    </select>
</p>
