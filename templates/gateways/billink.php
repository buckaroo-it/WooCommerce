<?php
/**
 * The Template for displaying bilink gateway template
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
?>


<fieldset id="buckaroo_billink_b2b">
	<p class="form-row form-row-wide validate-required">
		<?php echo esc_html_e( 'Fill required fields if bill in on the company:', 'wc-buckaroo-bpe-gateway' ); ?>
	</p>
	<p class="form-row form-row-wide validate-required">
		<label for="buckaroo-billink-company-coc-registration">
			<?php echo esc_html_e( 'COC (KvK) number:', 'wc-buckaroo-bpe-gateway' ); ?>
			<span class="required">*</span>
		</label>
		
		<input
		id="buckaroo-billink-company-coc-registration"
		name="buckaroo-billink-company-coc-registration"
		class="input-text"
		type="text"
		maxlength="250"
		autocomplete="off"
		value="" />
	</p>

	<p class="form-row form-row-wide">
		<label for="buckaroo-billink-VatNumber">
			<?php echo esc_html_e( 'VAT number:', 'wc-buckaroo-bpe-gateway' ); ?>
		</label>
		<input
		id="buckaroo-billink-VatNumber"
		name="buckaroo-billink-VatNumber"
		class="input-text"
		type="text"
		maxlength="250"
		autocomplete="off"
		value="" />
	</p>
	
	<p class="form-row form-row-wide validate-required">
		<a
		href="https://www.billink.nl/app/uploads/2021/05/Gebruikersvoorwaarden-Billink_V11052021.pdf"
		target="_blank">
			<?php echo esc_html_e( 'Accept terms of use', 'wc-buckaroo-bpe-gateway' ); ?>:
		</a>
		<span class="required">*</span>
		<input
		id="buckaroo-billink-accept"
		name="buckaroo-billink-accept"
		type="checkbox"
		value="ON" />
	</p>

	<p class="required" style="float:right;">
		* <?php echo esc_html_e( 'Required', 'wc-buckaroo-bpe-gateway' ); ?>
	</p>
</fieldset>
<fieldset id="buckaroo_billink_b2c">
	<?php
	$this->getPaymentTemplate( 'partial_gender_field' );
	$this->getPaymentTemplate( 'partial_birth_field' );
	?>

	<p class="form-row form-row-wide validate-required">
		<a
		href="https://www.billink.nl/app/uploads/2021/05/Gebruikersvoorwaarden-Billink_V11052021.pdf"
		target="_blank">
			<?php echo esc_html_e( 'Accept terms of use', 'wc-buckaroo-bpe-gateway' ); ?>:
		</a><span class="required">*</span>
		<input
		id="buckaroo-billink-accept"
		name="buckaroo-billink-accept"
		type="checkbox"
		value="ON" />
	</p>

	<p class="required" style="float:right;">
		* <?php echo esc_html_e( 'Required', 'wc-buckaroo-bpe-gateway' ); ?>
	</p>
</fieldset>

<?php if ( ! empty( $this->getScalarCheckoutField( 'ship_to_different_address' ) ) ) { ?>
	<input
	id="buckaroo-billink-shipping-differ"
	name="buckaroo-billink-shipping-differ"
	class=""
	type="hidden"
	value="1" />
	<?php
}
$this->getPaymentTemplate( 'financial_warning' );
