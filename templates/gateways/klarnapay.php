<?php
/**
 * The Template for displaying karnapay gateway template
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

$customerPhone = $this->getScalarCheckoutField( 'billing_phone' );
$country       = $this->getScalarCheckoutField( 'billing_country' );

if ( strtoupper( $country ) == 'NL' && strtolower( $this->klarnaPaymentFlowId ) !== 'pay' ) :
	?>
	<div class="woocommerce-error">
		<p>
			<?php
				echo esc_html_e( 'Payment method is not supported for country ', 'wc-buckaroo-bpe-gateway' ) . '(' . esc_html_e( $country ) . ')';
			?>
		</p>
	</div>
	<?php
endif;
?>

<fieldset>
	<?php
	$this->getPaymentTemplate( 'partial_gender_field' );
	?>

	<p class="form-row validate-required">
		<label for="<?php echo esc_attr( $this->getKlarnaSelector() ); ?>-phone">
			<?php echo esc_html_e( 'Phone:', 'wc-buckaroo-bpe-gateway' ); ?>
			<span class="required">*</span>
		</label>
		<input id="<?php echo esc_attr( $this->getKlarnaSelector() ); ?>-phone"
		name="<?php echo esc_attr( $this->getKlarnaSelector() ); ?>-phone"
		class="input-tel"
		type="tel"
		autocomplete="off"
		value="<?php echo esc_html( $customerPhone ) ?? ''; ?>">
	</p>

	<?php if ( ! empty( $this->getScalarCheckoutField( 'ship_to_different_address' ) ) ) { ?>
	<input
	id="<?php echo esc_attr( $this->getKlarnaSelector() ); ?>-shipping-differ"
	name="<?php echo esc_attr( $this->getKlarnaSelector() ); ?>-shipping-differ"
	class=""
	type="hidden"
	value="1" />
	<?php } ?>

	<p class="required" style="float:right;">*
		<?php echo esc_html_e( 'Required', 'wc-buckaroo-bpe-gateway' ); ?>
	</p>
	<?php $this->getPaymentTemplate( 'financial_warning' ); ?>
</fieldset>