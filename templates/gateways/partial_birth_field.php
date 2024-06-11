<?php
/**
 * The Template for displaying afterpay tos gateway template
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

$section_id = str_replace( '_', '-', $this->id );
?>
<p class="form-row form-row-wide validate-required">
	<label for="<?php echo esc_attr( $section_id ); ?>-birthdate">
		<?php echo esc_html_e( 'Birthdate (format DD-MM-YYYY):', 'wc-buckaroo-bpe-gateway' ); ?>
		<span class="required">*</span>
	</label>

	<input
	id="<?php echo esc_attr( $section_id ); ?>-birthdate"
	name="<?php echo esc_attr( $section_id ); ?>-birthdate"
	class="input-text"
	type="text"
	maxlength="250"
	autocomplete="off"
	value=""
	placeholder="DD-MM-YYYY" />
</p>