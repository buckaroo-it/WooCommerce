<?php

/**
 * The Template for displaying ideal gateway template
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

if ( $this->canShowIssuers() ) {
	?>
	<fieldset style="background: none">
		<p class="form-row form-row-wide">
			<select name="buckaroo-ideal-issuer" id="buckaroo-ideal-issuer">
				<option value="0" style="color: grey !important">
					<?php echo esc_html_e( 'Select your bank', 'wc-buckaroo-bpe-gateway' ); ?>
				</option>
				<?php foreach ( BuckarooIDeal::getIssuerList() as $key => $issuer ) : ?>
					<div>
						<option value="<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html_e( $issuer['name'], 'wc-buckaroo-bpe-gateway' ); ?>
						</option>
					</div>
				<?php endforeach ?>
			</select>
		</p>
	</fieldset>
	<?php
}
