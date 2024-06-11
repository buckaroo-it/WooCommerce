<?php
/**
 * The Template for displaying afterpay b2b gateway template
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

?>

<p class="form-row form-row-wide validate-required">
	<label for="buckaroo-afterpay-b2b">
		<?php echo esc_html_e( 'Checkout for company', 'wc-buckaroo-bpe-gateway' ); ?>
		<input 
		id="buckaroo-afterpay-b2b" 
		name="buckaroo-afterpay-b2b" 
		type="checkbox" value="ON" />
	</label>
</p>

<span id="showB2BBuckaroo" style="display:none">
	<p class="form-row form-row-wide validate-required">
		<?php echo esc_html_e( 'Fill required fields if bill in on the company:', 'wc-buckaroo-bpe-gateway' ); ?>
	</p>
	<p class="form-row form-row-wide validate-required">
		<label for="buckaroo-afterpay-company-coc-registration">
			<?php echo esc_html_e( 'COC (KvK) number:', 'wc-buckaroo-bpe-gateway' ); ?><span class="required">*</span>
		</label>
		<input 
		id="buckaroo-afterpay-company-coc-registration" 
		name="buckaroo-afterpay-company-coc-registration"
		class="input-text" 
		type="text" 
		maxlength="250" 
		autocomplete="off" 
		value="" />
	</p>
	<p class="form-row form-row-wide validate-required">
		<label for="buckaroo-afterpay-company-name">
			<?php echo esc_html_e( 'Name of the organization:', 'wc-buckaroo-bpe-gateway' ); ?><span class="required">*</span>
		</label>
		<input 
		id="buckaroo-afterpay-company-name" 
		name="buckaroo-afterpay-company-name" 
		class="input-text"
		type="text" 
		maxlength="250" 
		autocomplete="off" 
		value="" />
	</p>
</span>