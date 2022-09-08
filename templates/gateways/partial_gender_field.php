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
$genderVal = getGenderValues($section_id);
?>
<p class="form-row">
    <label for="<?php echo esc_attr($section_id)?>-gender">
        <?php echo esc_html_e('Gender:', 'wc-buckaroo-bpe-gateway') ?>
        <span class="required">*</span>
    </label>
    <select name="<?php echo esc_attr($section_id)?>-gender" id="<?php echo esc_attr($section_id)?>-gender">
        <option value="<?php echo $genderVal['male']; ?>"><?php echo esc_html_e('He/him', 'wc-buckaroo-bpe-gateway') ?></option>
        <option value="<?php echo $genderVal['female']; ?>"><?php echo esc_html_e('She/her', 'wc-buckaroo-bpe-gateway') ?></option>

        <?php if($section_id != 'buckaroo-klarnapay' && $section_id != 'buckaroo-klarnapii') { ?>
        
            <option value="<?php echo $genderVal['they']; ?>"><?php echo esc_html_e('They/them', 'wc-buckaroo-bpe-gateway') ?></option>
            <option value="<?php echo $genderVal['unknown']; ?>"><?php echo esc_html_e('I prefer not to say', 'wc-buckaroo-bpe-gateway') ?></option>

        <?php } ?>
    </select>
</p>
