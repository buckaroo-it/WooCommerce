<?php
/**
 * The Template for displaying payperemail gateway template
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

$customerFirstName = $this->getScalarCheckoutField( 'billing_first_name' );
$customerLastName  = $this->getScalarCheckoutField( 'billing_last_name' );
$customerEmail     = $this->getScalarCheckoutField( 'billing_email' );

?>
<fieldset>

<?php
	$this->getPaymentTemplate( 'partial_gender_field' );
?>

<p class="form-row validate-required">
	<label for="buckaroo-payperemail-firstname">
		<?php echo esc_html_e( 'First Name:', 'wc-buckaroo-bpe-gateway' ); ?>
		<span class="required">*</span>
	</label>
	<input
	id="buckaroo-payperemail-firstname"
	name="buckaroo-payperemail-firstname"
	class="input-text"
	type="text"
	autocomplete="off"
	value="<?php echo esc_html( $customerFirstName ) ?? ''; ?>">
</p>

<p class="form-row validate-required">
	<label for="buckaroo-payperemail-lastname">
		<?php echo esc_html_e( 'Last Name:', 'wc-buckaroo-bpe-gateway' ); ?>
		<span class="required">*</span>
	</label>
	<input
	id="buckaroo-payperemail-lastname"
	name="buckaroo-payperemail-lastname"
	class="input-text"
	type="text"
	autocomplete="off"
	value="<?php echo esc_html( $customerLastName ) ?? ''; ?>">
</p>

<p class="form-row validate-required">
	<label for="buckaroo-payperemail-email">
		<?php echo esc_html_e( 'Email:', 'wc-buckaroo-bpe-gateway' ); ?>
		<span class="required">*</span>
	</label>
	<input
	id="buckaroo-payperemail-email"
	name="buckaroo-payperemail-email"
	type="email"
	autocomplete="off"
	value="<?php echo esc_html( $customerEmail ) ?? ''; ?>">
</p>

<p class="required" style="float:right;">
	* <?php echo esc_html_e( 'Required', 'wc-buckaroo-bpe-gateway' ); ?>
</p>
</fieldset>