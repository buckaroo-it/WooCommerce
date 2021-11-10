<?php
/**
 * The Template for displaying afterpaynew gateway template
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
$customerPhone = $this->geCheckoutField('billing_phone');

$country = $this->geCheckoutField('billing_country');
$country = !empty($country) ? $country : $this->country;

?>


<fieldset>
    <?php if ($country == "FI") {?>
    <p class="form-row form-row-wide validate-required">
        <label for="buckaroo-afterpaynew-IdentificationNumber">
            <?php echo _e('Identification Number', 'wc-buckaroo-bpe-gateway') ?>
            <span class="required">*</span>
        </label>

        <input 
        id="buckaroo-afterpaynew-IdentificationNumber"
        name="buckaroo-afterpaynew-IdentificationNumber"
        class="input-text"
        type="text"
        maxlength="250"
        autocomplete="off"
        value="" />
    </p>
    <?php }?>

    <?php if (in_array($country, ["BE", "NL"])) {
        $this->getPaymentTemplate('partial_gender_field');
        $this->getPaymentTemplate('partial_birth_field');
        ?>
    <p class="form-row validate-required">
        <label for="buckaroo-afterpaynew-phone">
            <?php echo _e('Phone:', 'wc-buckaroo-bpe-gateway') ?>
            <span class="required">*</span>
        </label>
        <input
        id="buckaroo-afterpaynew-phone"
        name="buckaroo-afterpaynew-phone"
        class="input-tel"
        type="tel"
        autocomplete="off"
        value="<?php echo $customerPhone; ?>">
    </p>
    <?php }?>

    <?php if (!empty($this->geCheckoutField('ship_to_different_address'))) {?>
    <input
    id="buckaroo-afterpaynew-shipping-differ"
    name="buckaroo-afterpaynew-shipping-differ"
    class=""
    type="hidden"
    value="1" />
    <?php }
    $this->getPaymentTemplate('partial_afterpay_tos');
    ?>
</fieldset>