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

$fieldName = $this->id === 'buckaroo_afterpaynew' ? 'buckaroo-afterpaynew-accept' : 'buckaroo-afterpay-accept';
$tosLinks  = array(
	'NL' => 'https://documents.myafterpay.com/consumer-terms-conditions/nl_nl/',
	'BE' => array(
		array(
			'link'  => 'https://documents.myafterpay.com/consumer-terms-conditions/nl_be/',
			'label' => 'Riverty conditions (Dutch)',
		),
		array(
			'link'  => 'https://documents.myafterpay.com/consumer-terms-conditions/fr_be/',
			'label' => 'Riverty conditions (French)',
		),
	),
	'DE' => 'https://documents.myafterpay.com/consumer-terms-conditions/de_at/',
	'FI' => 'https://documents.myafterpay.com/consumer-terms-conditions/fi_fi/',
	'AT' => 'https://documents.myafterpay.com/consumer-terms-conditions/de_at/',
);
$country   = $this->getScalarCheckoutField( 'billing_country' );
$country   = ! empty( $country ) ? $country : $this->country;

// set default to NL
if ( ! isset( $tosLinks[ $country ] ) ) {
	$country = 'NL';
}

$tos = $tosLinks[ $country ];

?>

<p class="form-row form-row-wide validate-required">
<?php
if ( ! is_array( $tos ) ) {
	?>
	<a 
	href="<?php echo esc_url( $tos ); ?>"
	target="_blank">
		<?php echo esc_html_e( 'Accept Riverty conditions:', 'wc-buckaroo-bpe-gateway' ); ?>
	</a>
	<?php
} else {
	echo esc_html_e( 'Accept Riverty conditions:', 'wc-buckaroo-bpe-gateway' );
}
?>
	<span class="required">*</span> 
	<input id="<?php echo esc_attr( $fieldName ); ?>"
	name="<?php echo esc_attr( $fieldName ); ?>"
	type="checkbox"
	value="ON" />
	<?php
	if ( is_array( $tos ) ) {
		foreach ( $tos as $tosElement ) {
			?>
			<br>
			<a href="<?php echo esc_url( $tosElement['link'] ); ?>" target="_blank">
				<?php echo esc_html_e( $tosElement['label'], 'wc-buckaroo-bpe-gateway' ); ?>
			</a>
			<?php
		}
	}
	?>
</p>
	
<p class="required" style="float:right;">*
	<?php echo esc_html_e( 'Required', 'wc-buckaroo-bpe-gateway' ); ?>
</p>
