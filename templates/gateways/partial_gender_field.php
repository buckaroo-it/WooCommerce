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

$allGenders = BuckarooConfig::getAllGendersForPaymentMethods();
$genderVal  = $allGenders[ $section_id ] ?? array();
?>
<p class="form-row">
	<label for="<?php echo esc_attr( $section_id ); ?>-gender">
		<?php echo __( 'Gender:', 'wc-buckaroo-bpe-gateway' ); ?>
		<span class="required">*</span>
	</label>
	<select name="<?php echo esc_attr( $section_id ); ?>-gender" id="<?php echo esc_attr( $section_id ); ?>-gender">
		<?php
		foreach ( $genderVal as $key => $value ) {
			$translatedLabel = BuckarooConfig::translateGender( $key );
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $translatedLabel ) . '</option>';
		}
		?>
	</select>
</p>
