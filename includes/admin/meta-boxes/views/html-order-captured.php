<?php
/**
 * Show order capture
 *
 * @var object $singleCapture The capture object.
 * @package WooCommerce\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<tr class="capture <?php echo ( ! empty( $class ) ) ? esc_attr( $class ) : ''; ?>" data-order_capture_id="<?php echo esc_attr( $singleCapture['id'] ); ?>">
	<td class="thumb"><div></div></td>

	<td class="name">
		<?php
			printf(
				esc_html__( 'Capture - #%s', 'woocommerce' ),
				esc_attr( $singleCapture['id'] )
			);
			?>
		<input type="hidden" class="order_capture_id" name="order_capture_id[]" value="<?php echo esc_attr( $singleCapture['id'] ); ?>" />
	</td>

	<td class="item_cost" width="1%">&nbsp;</td>
	<td class="quantity" width="1%">&nbsp;</td>

	<td class="line_cost" width="1%">
		<div class="view">
			<?php
			echo wp_kses_post(
				wc_price( str_replace( ',', '.', $singleCapture['amount'] ), array( 'currency' => $singleCapture['currency'] ) )
			);
			?>
		</div>
	</td>

	<?php
	if ( wc_tax_enabled() ) :
		$total_taxes = count( $order_taxes );
		?>
		<?php for ( $i = 0;  $i < $total_taxes; $i++ ) : ?>
			<td class="line_tax" width="1%"></td>
		<?php endfor; ?>
	<?php endif; ?>

	<td class="wc-order-edit-line-item">
		<div class="wc-order-edit-line-item-actions">
			<a class="delete_capture" href="#"></a>
		</div>
	</td>
</tr>
