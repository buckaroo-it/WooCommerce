<?php

require_once __DIR__ . '/../../library/api/idin.php';

if ( BuckarooIdin::checkCurrentUserIsVerified() ) {
	?>
	<div id="buckaroo_idin" class="buckaroo-idin buckaroo-idin-passed form-row">
		<h3 id="buckaroo_idin_heading"><?php esc_html_e( 'Age verification', 'wc-buckaroo-bpe-gateway' ); ?></h3>
		<fieldset>
			<div>
				<img class="buckaroo_idin_logo" src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . '../library/buckaroo_images/idin_logo.svg' ); ?>" />
				<p class="buckaroo_idin_prompt"><?php esc_html_e( 'You have verified your age already', 'wc-buckaroo-bpe-gateway' ); ?></p>
			</div>
		</fieldset>
	</div>
	<?php
} else {
	?>
	<style>
		.woocommerce-checkout-payment {
			display: none;
		}
	</style>

	<div id="buckaroo_idin" class="buckaroo-idin buckaroo-idin-not-passed form-row">
		<h3 id="buckaroo_idin_heading"><?php esc_html_e( 'Age verification', 'wc-buckaroo-bpe-gateway' ); ?></h3>
		<fieldset>
			<div>
				<img class="buckaroo_idin_logo" src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . '../library/buckaroo_images/idin_logo.svg' ); ?>" />
				<p class="buckaroo_idin_prompt">
					<?php esc_html_e( 'To continue you must verify your age using iDIN', 'wc-buckaroo-bpe-gateway' ); ?>
				</p>

				<p class="form-row form-row-wide">
					<select id='buckaroo-idin-issuer'>
						<option value='0' style='color: grey !important'>
							<?php esc_html_e( 'Select your bank', 'wc-buckaroo-bpe-gateway' ); ?>
						</option>
						<?php foreach ( BuckarooIdin::getIssuerList() as $issuer ) : ?>
							<div>
								<option value='<?php echo esc_attr( $issuer['servicename'] ); ?>'>
									<?php esc_html_e( $issuer['displayname'], 'wc-buckaroo-bpe-gateway' ); ?>
								</option>
							</div>
						<?php endforeach ?>
					</select>
				</p>


				<button type="button" class="button alt" id="buckaroo-idin-verify-button"
						value="<?php esc_html_e( 'Verify your age via iDIN', 'wc-buckaroo-bpe-gateway' ); ?>">
					<?php esc_html_e( 'Verify your age via iDIN', 'wc-buckaroo-bpe-gateway' ); ?>
				</button>
			</div>
		</fieldset>
	</div>
	<?php
}
?>