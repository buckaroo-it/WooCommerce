<?php

/**
 * The Template for displaying in3 gateway template
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

$country        = $this->getScalarCheckoutField( 'billing_country' );
$country        = ! empty( $country ) ? $country : $this->country;
$customer_phone = $this->getScalarCheckoutField( 'billing_phone' );

?>
<fieldset>
	<?php
	if ( $country == 'NL' ) :
		$this->getPaymentTemplate( 'partial_birth_field' );
		?>
		<?php
	endif;
	$this->getPaymentTemplate( 'financial_warning' );
	?>

	<?php if ( strlen( trim( $customer_phone ) ) === 0 ) : ?>
	<p class="form-row validate-required">
		<label for="buckaroo-in3-phone">
			<?php echo esc_html_e( 'Phone:', 'wc-buckaroo-bpe-gateway' ); ?>
			<span class="required">*</span>
		</label>

		<input id="buckaroo-in3-phone"
		name="buckaroo-in3-phone"
		class="input-tel"
		type="tel"
		autocomplete="off">
	</p>
	<?php endif; ?>
</fieldset>