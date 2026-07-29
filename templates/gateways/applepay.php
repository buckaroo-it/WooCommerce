<?php

/**
 * The Template for the Apple Pay standard checkout payment method
 * (classic / shortcode checkout).
 *
 * No Apple Pay button is rendered here — the standard method is triggered from
 * the normal "Place Order" action and only authorises the payment. This markup
 * just provides the (hidden) anchor element for the JS instance and the hidden
 * field that carries the authorised token into the WooCommerce submission.
 * Billing and shipping come from the checkout form.
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
<div class="buckaroo-applepay-checkout-method">
    <div class="applepay-checkout-button-container"
         data-button-style="<?php echo esc_attr($buttonStyle); ?>"
         style="display:none;"></div>
    <input type="hidden" name="paymentData" class="buckaroo-applepay-payment-data" value="" />
</div>
