<?php
/**
 * The Template for displaying sepadirect gateway template
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

$customer_name  = $this->getScalarCheckoutField( 'billing_first_name' );
$customer_name .= ' ' . $this->getScalarCheckoutField( 'billing_last_name' );

?>

<fieldset>
	<p class="form-row form-row-wide validate-required">
		<label for="buckaroo-sepadirectdebit-accountname">
			<?php echo esc_html_e( 'Bank account holder:', 'wc-buckaroo-bpe-gateway' ); ?>
			<span class="required">*</span>
		</label>
		<input
		id="buckaroo-sepadirectdebit-accountname"
		name="buckaroo-sepadirectdebit-accountname"
		class="input-text"
		type="text"
		maxlength="250"
		autocomplete="off"
		value="<?php echo esc_html( $customer_name ); ?>" />
	</p>
	<p class="form-row form-row-wide validate-required">
		<label for="buckaroo-sepadirectdebit-iban">
			<?php echo esc_html_e( 'IBAN:', 'wc-buckaroo-bpe-gateway' ); ?>
			<span class="required">*</span>
		</label>
		<input
		id="buckaroo-sepadirectdebit-iban"
		name="buckaroo-sepadirectdebit-iban"
		class="input-text"
		type="text"
		maxlength="25"
		autocomplete="off"
		value=""
		/>
	</p>
	<p class="form-row form-row-wide">
		<label for="buckaroo-sepadirectdebit-bic">
			<?php echo esc_html_e( 'BIC:', 'wc-buckaroo-bpe-gateway' ); ?>
		</label>
		<input
		id="buckaroo-sepadirectdebit-bic"
		name="buckaroo-sepadirectdebit-bic"
		class="input-text"
		type="text"
		maxlength="11"
		autocomplete="off"
		value=""/>
	</p>
	<p class="required" style="float:right;">
		* <?php echo esc_html_e( 'Required', 'wc-buckaroo-bpe-gateway' ); ?>
	</p>
</fieldset>