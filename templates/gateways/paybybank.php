<?php

/**
 * The Template for displaying paybybank gateway template
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

$issuers        = BuckarooPayByBank::getIssuerList();
$selectedIssuer = BuckarooPayByBank::getActiveIssuerCode();
?>
<fieldset style="background: none">
	<?php if ( $this->get_option( 'displaymode' ) === 'dropdown' ) { ?>
		<div class="form-row form-row-wide">
			<select name="buckaroo-paybybank-issuer" id="buckaroo-paybybank-issuer">
				<option value="0" style="color: grey !important">
					<?php echo esc_html_e( 'Select your bank', 'wc-buckaroo-bpe-gateway' ); ?>
				</option>
				<?php foreach ( $issuers as $key => $issuer ) : ?>
				<div>
					<option value="<?php echo esc_attr( $key ); ?>" 
												<?php
												if ( isset( $issuer['selected'] ) && $issuer['selected'] === true ) {
													?>
selected <?php } ?> id="bankMethod<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html_e( $issuer['name'], 'wc-buckaroo-bpe-gateway' ); ?>
					</option>
				</div>
				<?php endforeach ?>
			</select>
		</div>
	<?php } else { ?>

	<input type="hidden" name="buckaroo-paybybank-issuer" class="bk-paybybank-real-value" value="<?php echo esc_attr( $selectedIssuer ); ?>">
	<div class="form-row form-row-wide bk-paybybank-input bk-paybybank-mobile" style="display: none;">
		<select class="buckaroo-paybybank-select">
			<option value style="color: grey !important">
				<?php echo esc_html_e( 'Select your bank', 'wc-buckaroo-bpe-gateway' ); ?>
			</option>
			<?php foreach ( $issuers as $key => $issuer ) : ?>
			<div>
				<option value="<?php echo esc_attr( $key ); ?>" 
											<?php
											if ( isset( $issuer['selected'] ) && $issuer['selected'] === true ) {
												?>
selected <?php } ?> id="bankMethod<?php echo esc_attr( $key ); ?>">
					<?php echo esc_html_e( $issuer['name'], 'wc-buckaroo-bpe-gateway' ); ?>
				</option>
			</div>
			<?php endforeach ?>
		</select>
	</div>

	<div class="bk-paybybank-input bk-paybybank-not-mobile">
		<div class="form-row form-row-wide bk-paybybank-selector">
			<?php foreach ( $issuers as $key => $issuer ) : ?>
				<div class="custom-control custom-radio bank-control">
					<input name="buckaroo-paybybank-radio-issuer" type="radio" 
					<?php
					if ( isset( $issuer['selected'] ) && $issuer['selected'] === true ) {
						?>
checked <?php } ?> id="radio-bankMethod<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $key ); ?>" class="custom-control-input bank-method-input bk-paybybank-radio">
					<label class="custom-control-label bank-method-label" for="radio-bankMethod<?php echo esc_attr( $key ); ?>">
						<img src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . '../library/buckaroo_images/ideal/' . $issuer['logo'] ); ?>" wdith="45" class="bank-method-image" alt="<?php echo esc_html_e( $issuer['name'], 'wc-buckaroo-bpe-gateway' ); ?>" title="<?php echo esc_html_e( $issuer['name'], 'wc-buckaroo-bpe-gateway' ); ?>">
						<strong><?php echo esc_html_e( $issuer['name'], 'wc-buckaroo-bpe-gateway' ); ?></strong>
					</label>
				</div>
			<?php endforeach ?>
		</div>
		<div class="bk-paybybank-toggle-list">
			<div class="bk-toggle-wrap">
				<div class="bk-toggle-text" text-less="<?php echo esc_html_e( 'Less banks', 'wc-buckaroo-bpe-gateway' ); ?>" text-more="<?php echo esc_html_e( 'More banks', 'wc-buckaroo-bpe-gateway' ); ?>">
					<?php echo esc_html_e( 'More banks', 'wc-buckaroo-bpe-gateway' ); ?>
				</div>
				<div class="bk-toggle bk-toggle-down"></div>
			</div>
		</div>
	</div>
</fieldset>

<?php } ?>