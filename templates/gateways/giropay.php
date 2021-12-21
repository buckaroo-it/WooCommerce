<?php
/**
 * The Template for displaying giropay gateway template
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
<fieldset>
    <p class="form-row form-row-wide">
        <label for="buckaroo-giropay-bancaccount">
            <?php echo _e('BIC:', 'wc-buckaroo-bpe-gateway') ?>
            <span class="required">*</span>
        </label>
        <input
        id="buckaroo-giropay-bancaccount"
        name="buckaroo-giropay-bancaccount"
        class="input-text card-number"
        type="text"
        maxlength="11"
        autocomplete="off"
        value="" 
        />
    </p>
    <p class="required" style="float:right;">
        * <?php echo _e('Required', 'wc-buckaroo-bpe-gateway') ?>
    </p>
</fieldset>