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

defined( 'ABSPATH' ) || exit;

// set customer phone
$customer_phone = $this->getScalarCheckoutField( 'billing_phone' );

$country = $this->getScalarCheckoutField( 'billing_country' );
$country = ! empty( $country ) ? $country : $this->country;

?>


<fieldset>
	<?php if ( $country == 'FI' ) { ?>
	<p class="form-row form-row-wide validate-required">
		<label for="buckaroo-afterpaynew-identification-number">
			<?php echo esc_html_e( 'Identification Number', 'wc-buckaroo-bpe-gateway' ); ?>
			<span class="required">*</span>
		</label>

		<input 
		id="buckaroo-afterpaynew-identification-number"
		name="buckaroo-afterpaynew-identification-number"
		class="input-text"
		type="text"
		maxlength="250"
		autocomplete="off"
		value="" />
	</p>
	<?php } ?>

	<?php
	if ( in_array( $country, array( 'BE', 'NL', 'DE' ) ) ) {
		$this->getPaymentTemplate( 'partial_birth_field' );
		?>
	<p class="form-row validate-required">
		<label for="buckaroo-afterpaynew-phone">
			<?php echo esc_html_e( 'Phone:', 'wc-buckaroo-bpe-gateway' ); ?>
			<span class="required">*</span>
		</label>
		<input
		id="buckaroo-afterpaynew-phone"
		name="buckaroo-afterpaynew-phone"
		class="input-tel"
		type="tel"
		autocomplete="off"
		value="<?php echo esc_html( $customer_phone ); ?>">
	</p>
	<?php } ?>

	<?php if ( $country == 'NL' && WC_Gateway_Buckaroo_Afterpaynew::CUSTOMER_TYPE_B2C !== $this->customer_type ) { ?>
	<p class="form-row form-row-wide validate-required">
		<label for="buckaroo-afterpaynew-company-coc-registration">
			<?php echo esc_html_e( 'CoC-number:', 'wc-buckaroo-bpe-gateway' ); ?>
			<span class="required">*</span>
		</label>

		<input 
		id="buckaroo-afterpaynew-company-coc-registration"
		name="buckaroo-afterpaynew-company-coc-registration"
		class="input-text"
		type="text"
		maxlength="250"
		autocomplete="off"
		value="" />
	</p>
	<?php } ?>

	<?php if ( ! empty( $this->getScalarCheckoutField( 'ship_to_different_address' ) ) ) { ?>
	<input
	id="buckaroo-afterpaynew-shipping-differ"
	name="buckaroo-afterpaynew-shipping-differ"
	class=""
	type="hidden"
	value="1" />
		<?php
	}
	$this->getPaymentTemplate( 'partial_afterpay_tos' );
	$this->getPaymentTemplate( 'financial_warning' );
	?>
	
</fieldset>