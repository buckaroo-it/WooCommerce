<?php

/**
 * The Template for displaying the Apple Pay standard checkout payment method
 * (classic / shortcode checkout).
 *
 * Renders Apple's official <apple-pay-button> web component and a hidden field
 * that carries the authorised Apple Pay token into the normal WooCommerce
 * checkout submission. The Apple sheet only authorises payment; billing and
 * shipping come from the WooCommerce checkout form.
 *
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

$buttonStyle = $this->get_option('button_style', 'black');
?>
<fieldset class="buckaroo-applepay-checkout-method" style="background: none">
    <div class="applepay-checkout-button-container"
         data-button-style="<?php echo esc_attr($buttonStyle); ?>">
    </div>
    <input type="hidden" name="paymentData" class="buckaroo-applepay-payment-data" value="" />
    <p class="buckaroo-applepay-checkout-hint">
        <?php esc_html_e('Click the Apple Pay button to authorise your payment, then place the order.', 'wc-buckaroo-bpe-gateway'); ?>
    </p>
</fieldset>
