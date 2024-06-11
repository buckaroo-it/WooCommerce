<?php
require_once __DIR__ . '/../../library/api/idin.php';
?>
<div id="buckaroo_idin_cart" class="buckaroo-idin-cart form-row">
	<fieldset>
		<div>
			<img class="buckaroo_idin_logo" src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . '../library/buckaroo_images/idin_logo.svg' ); ?>" />
			<p class="buckaroo_idin_prompt">
				<?php
					esc_html_e( 'You must be 18 or older to pay for the products below', 'wc-buckaroo-bpe-gateway' );
				?>
			</p>
		</div>
	</fieldset>
</div>
