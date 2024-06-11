<?php
/**
 * The Template for displaying creditcard gateway template
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

$creditCardMethod = isset( $this->creditcardmethod ) ? $this->creditcardmethod : 'redirect';


$customer_name  = $this->getScalarCheckoutField( 'billing_first_name' );
$customer_name .= ' ' . $this->getScalarCheckoutField( 'billing_last_name' );
?>


<fieldset>
	<div class="method--bankdata">
		<?php
		if ( $this instanceof Buckaroo_Creditcard_Single ) {
			?>
		<input 
		type="hidden" 
		name="<?php echo esc_attr( $this->id ); ?>-creditcard-issuer" 
		value="<?php echo esc_attr( str_replace( 'buckaroo_creditcard_', '', $this->id ) ); ?>"
		/>
			<?php
		} else {
			?>
			<p class="form-row form-row-wide">
				<select
				name='<?php echo esc_attr( $this->id ); ?>-creditcard-issuer'
				id='buckaroo-creditcard-issuer'>
					<option value='0' style='color: grey !important'>
						<?php echo esc_html_e( 'Select your credit card:', 'wc-buckaroo-bpe-gateway' ); ?>
					</option>
					<?php foreach ( $this->getCardsList() as $issuer ) : ?>
					<div>
						<option value='<?php echo esc_attr( $issuer['servicename'] ); ?>'>
							<?php echo esc_html_e( $issuer['displayname'], 'wc-buckaroo-bpe-gateway' ); ?>
						</option>
					</div>
					<?php endforeach ?>
				</select>
			</p>
			<?php
		}
		if ( $creditCardMethod == 'encrypt' && $this->isSecure() ) :
			?>

		<p class="form-row">
			<label class="buckaroo-label" for="<?php echo esc_attr( $this->id ); ?>-cardname">
				<?php echo esc_html_e( 'Cardholder Name:', 'wc-buckaroo-bpe-gateway' ); ?>
				<span class="required">*</span>
			</label>

			<input
			type="text"
			name="<?php echo esc_attr( $this->id ); ?>-cardname"
			id="<?php echo esc_attr( $this->id ); ?>-cardname"
			placeholder="<?php echo esc_html_e( 'Cardholder Name:', 'wc-buckaroo-bpe-gateway' ); ?>"
			class="cardHolderName input-text"
			maxlength="250"
			autocomplete="off"
			value="<?php echo esc_html( $customer_name ) ?? ''; ?>">
		</p>

		<p class="form-row">
			<label class="buckaroo-label" for="<?php echo esc_attr( $this->id ); ?>-cardnumber">
				<?php echo esc_html_e( 'Card Number:', 'wc-buckaroo-bpe-gateway' ); ?>
				<span class="required">*</span>
			</label>

			<input
			type="text"
			name="<?php echo esc_attr( $this->id ); ?>-cardnumber"
			id="<?php echo esc_attr( $this->id ); ?>-cardnumber"
			placeholder="<?php echo esc_html_e( 'Card Number:', 'wc-buckaroo-bpe-gateway' ); ?>"
			class="cardNumber input-text"
			maxlength="250"
			autocomplete="off"
			value="">
		</p>

		<p class="form-row">
			<label class="buckaroo-label" for="<?php echo esc_attr( $this->id ); ?>-cardmonth">
				<?php echo esc_html_e( 'Expiration Month:', 'wc-buckaroo-bpe-gateway' ); ?>
				<span class="required">*</span>
			</label>

			<input
			type="text"
			maxlength="2"
			name="<?php echo esc_attr( $this->id ); ?>-cardmonth"
			id="<?php echo esc_attr( $this->id ); ?>-cardmonth"
			placeholder="<?php echo esc_html_e( 'Expiration Month:', 'wc-buckaroo-bpe-gateway' ); ?>"
			class="expirationMonth input-text"
			maxlength="250"
			autocomplete="off"
			value="">
		</p>

		<p class="form-row">
			<label class="buckaroo-label" for="<?php echo esc_attr( $this->id ); ?>-cardyear">
				<?php echo esc_html_e( 'Expiration Year:', 'wc-buckaroo-bpe-gateway' ); ?>
				<span class="required">*</span>
			</label>
			<input
			type="text"
			maxlength="4"
			name="<?php echo esc_attr( $this->id ); ?>-cardyear"
			id="<?php echo esc_attr( $this->id ); ?>-cardyear"
			placeholder="<?php echo esc_html_e( 'Expiration Year:', 'wc-buckaroo-bpe-gateway' ); ?>"
			class="expirationYear input-text"
			maxlength="250"
			autocomplete="off"
			value="">
		</p>

		<p class="form-row">
			<label class="buckaroo-label" for="<?php echo esc_attr( $this->id ); ?>-cardcvc">
				<?php echo esc_html_e( 'CVC:', 'wc-buckaroo-bpe-gateway' ); ?>
				<span class="required">*</span>
			</label>
			<input
			type="password"
			maxlength="4"
			name="<?php echo esc_attr( $this->id ); ?>-cardcvc"
			id="<?php echo esc_attr( $this->id ); ?>-cardcvc"
			placeholder="<?php echo esc_html_e( 'CVC:', 'wc-buckaroo-bpe-gateway' ); ?>"
			class="cvc input-text"
			maxlength="250"
			autocomplete="off"
			value="">
		</p>

		<p class="form-row form-row-wide validate-required"></p>
		<p class="required" style="float:right;">*
			<?php echo esc_html_e( 'Required', 'wc-buckaroo-bpe-gateway' ); ?>
		</p>

		<input
		type="hidden"
		id="<?php echo esc_attr( $this->id ); ?>-encrypted-data"
		name="<?php echo esc_attr( $this->id ); ?>-encrypted-data"
		class="encryptedCardData input-text">
		<?php endif; ?>

	</div>
</fieldset>
