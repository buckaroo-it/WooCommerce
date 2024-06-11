<?php
/**
 * Order items HTML for meta box.
 *
 * @package WooCommerce/Admin
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$payment_gateway     = wc_get_payment_gateway_by_order( $order );
$line_items          = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );
$discounts           = $order->get_items( 'discount' );
$line_items_fee      = $order->get_items( 'fee' );
$line_items_shipping = $order->get_items( 'shipping' );

if ( wc_tax_enabled() ) {
	$order_taxes      = $order->get_taxes();
	$tax_classes      = WC_Tax::get_tax_classes();
	$classes_options  = wc_get_product_tax_class_options();
	$show_tax_columns = count( $order_taxes ) === 1;
}
?>
<style>
#buckaroo-order-capture .woocommerce_order_items_wrapper table.woocommerce_order_items th.line_tax {
	white-space: nowrap;
}

#order_line_items .capture-item td {
	padding-top: 10px;
	padding-bottom: 10px;
	
}

#order_line_items .capture-item {
	border: none;
}

#order_line_items .capture-item td.name
{
	padding-left: 10px;
	text-align: left;
}

#order_line_items .capture-item td.item_cost,
#order_line_items .capture-item td.quantity
{
	text-align: center;
}


#order_line_items .capture-item td.line_cost,
#order_line_items .capture-item td.line_tax
{
	text-align: right;
}

#order_shipping_line_items {
	margin-top: 15px;
}

.woocommerce_order_items_wrapper .shipping_spacer {
	height:50px;
}	

#order_shipping_line_items .display_meta td {
	border:  none;
}

#order_shipping_line_items .line_cost {
	text-align: right;
}

#order_captures .line_cost {
	text-align: right;
}

#order_captures tr, #order_captures td {
	border-bottom: none;
}


.capture-totals  {
	width: 100%!important;
	background-color: rgb(248, 248, 248);
	float: right;
	
	
}

.capture-totals .total {
	width: 120px;
	font-weight: bold;
	padding: 10px;
	padding-right: 30px;
	text-align: right;
}

.capture-totals .label {

	text-align: right;
}

#order_shipping_line_items td.line_tax {
	text-align: right;
}

.capture-actions {
	float: right;
	height: 40px;
	padding-right: 30px;
	padding-top:10px;
	
}

.wc-order-capture-items {
	padding-bottom: 10px;
	background-color: rgb(248, 248, 248);
}

</style>


<div class="woocommerce_order_items_wrapper wc-order-items-editable">
	<table cellpadding="0" cellspacing="0" class="woocommerce_order_items">
		<thead>
			<tr>
				<th class="item sortable" colspan="2" data-sort="string-ins"><?php esc_html_e( 'Item', 'woocommerce' ); ?></th>
				<?php do_action( 'woocommerce_admin_order_item_headers', $order ); ?>
				<th class="item_cost sortable" data-sort="float"><?php esc_html_e( 'Cost', 'woocommerce' ); ?></th>
				<th class="quantity sortable" data-sort="int"><?php esc_html_e( 'Qty', 'woocommerce' ); ?></th>
				<th class="line_cost sortable" data-sort="float"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
				<?php
				if ( ! empty( $order_taxes ) ) :
					foreach ( $order_taxes as $tax_id => $tax_item ) :
						$tax_class      = wc_get_tax_class_by_tax_id( $tax_item['rate_id'] );
						$tax_class_name = isset( $classes_options[ $tax_class ] ) ? $classes_options[ $tax_class ] : __( 'Tax', 'woocommerce' );
						$column_label   = ! empty( $tax_item['label'] ) ? $tax_item['label'] : __( 'Tax', 'woocommerce' );
						/* translators: %1$s: tax item name %2$s: tax class name  */
						$column_tip = sprintf( esc_html__( '%1$s (%2$s)', 'woocommerce' ), $tax_item['name'], $tax_class_name );
						?>
						<th class="line_tax tips" data-tip="<?php echo esc_attr( $column_tip ); ?>">
							<?php echo esc_attr( $column_label ); ?>
							<input type="hidden" class="order-tax-id" name="order_taxes[<?php echo esc_attr( $tax_id ); ?>]" value="<?php echo esc_attr( $tax_item['rate_id'] ); ?>">
							<a class="delete-order-tax" href="#" data-rate_id="<?php echo esc_attr( $tax_id ); ?>"></a>
						</th>
						<?php
					endforeach;
				endif;
				?>
				<th class="wc-order-capture-edit-line-item" width="1%">&nbsp;</th>
			</tr>
		</thead>
		<tbody id="order_line_items">
			<?php

			$captures = get_post_meta( $order->get_id(), '_wc_order_captures' ) ? get_post_meta( $order->get_id(), '_wc_order_captures' ) : false;

			foreach ( $line_items as $item_id => $item ) {
				do_action( 'woocommerce_before_order_item_' . $item->get_type() . '_html', $item_id, $item, $order );

				include 'html-order-item.php';

				do_action( 'woocommerce_order_item_' . $item->get_type() . '_html', $item_id, $item, $order );
			}

			do_action( 'woocommerce_admin_order_items_after_line_items', $order->get_id() );
			?>
		</tbody>
		<tbody>
				<tr><td class="shipping_spacer" colspan="<?php echo ( wc_tax_enabled() ? 7 : 6 ); ?>">&nbsp;</td></tr>
		</tbody>
		<tbody id="order_shipping_line_items">
			<?php
			$shipping_methods = WC()->shipping() ? WC()->shipping()->load_shipping_methods() : array();
			foreach ( $line_items_shipping as $item_id => $item ) {
				include 'html-order-shipping.php';
			}
			do_action( 'woocommerce_admin_order_items_after_shipping', $order->get_id() );
			?>
		</tbody>
		<?php
		if ( ! $captures ) {
			?>
		<tbody id="order_fee_line_items">
			<?php
			foreach ( $line_items_fee as $item_id => $item ) {
				include 'html-order-fee.php';
			}
			do_action( 'woocommerce_admin_order_items_after_fees', $order->get_id() );
			?>
		</tbody>
		<?php } ?>
		<tbody>
				<tr><td class="shipping_spacer" colspan="<?php echo ( wc_tax_enabled() ? 7 : 6 ); ?>">&nbsp;</td></tr>
		</tbody>		
		<tbody id="order_captures">
			<?php

			if ( $captures ) {
				?>
			<tr><td colspan="<?php echo ( wc_tax_enabled() ? 7 : 6 ); ?>"><strong>
				<?php echo esc_html__( 'Previous capture(s)', 'woocommerce' ); ?>
			</strong></td></tr>
				<?php

				foreach ( $captures as $singleCapture ) {

					include 'html-order-captured.php';
				}
				do_action( 'woocommerce_admin_order_items_after_captures', $order->get_id() );
			}
			?>
			<tr><td colspan="<?php echo ( wc_tax_enabled() ? 7 : 6 ); ?>">&nbsp;</td></tr>			
		</tbody>
	</table>
</div>
<div class="wc-order-data-row wc-order-capture-totals-items wc-order-items-editable">
	<?php
	$coupons = $order->get_items( 'coupon' );
	if ( $coupons ) :
		?>
		<div class="wc-used-coupons">
			<ul class="wc_coupon_list">
				<li><strong><?php esc_html_e( 'Coupon(s)', 'woocommerce' ); ?></strong></li>
				<?php
				foreach ( $coupons as $item_id => $item ) :
					$cp_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish' LIMIT 1;", $item->get_code() ) ); // phpcs:disable WordPress.WP.GlobalVariablesOverride.OverrideProhibited
					$class      = $order->is_editable() ? 'code editable' : 'code';
					?>
					<li class="<?php echo esc_attr( $class ); ?>">
						<?php if ( $cp_post_id ) : ?>
							<?php
							$post_url = apply_filters(
								'woocommerce_admin_order_item_coupon_url',
								add_query_arg(
									array(
										'post'   => $cp_post_id,
										'action' => 'edit',
									),
									admin_url( 'post.php' )
								),
								$item,
								$order
							);
							?>
							<a href="<?php echo esc_url( $post_url ); ?>" class="tips" data-tip="<?php echo esc_attr( wc_price( $item->get_discount(), array( 'currency' => $order->get_currency() ) ) ); ?>">
								<span><?php echo esc_html( $item->get_code() ); ?></span>
							</a>
						<?php else : ?>
							<span class="tips" data-tip="<?php echo esc_attr( wc_price( $item->get_discount(), array( 'currency' => $order->get_currency() ) ) ); ?>">
								<span><?php echo esc_html( $item->get_code() ); ?></span>
							</span>
						<?php endif; ?>
						<?php if ( $order->is_editable() ) : ?>
							<a class="remove-coupon" href="javascript:void(0)" aria-label="Remove" data-code="<?php echo esc_attr( $item->get_code() ); ?>"></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<table class="wc-order-totals capture-totals">
		<?php if ( 0 < $order->get_total_discount() ) : ?>
			<tr>
				<td class="label"><?php esc_html_e( 'Discount:', 'woocommerce' ); ?></td>
				<td width="1%"></td>
				<td class="total">
					<?php echo wp_kses_post( wc_price( $order->get_total_discount(), array( 'currency' => $order->get_currency() ) ) ); ?>
				</td>
			</tr>
		<?php endif; ?>

		<?php do_action( 'woocommerce_admin_order_totals_after_discount', $order->get_id() ); ?>

		<?php if ( $order->get_shipping_methods() ) : ?>
			<tr>
				<td class="label"><?php esc_html_e( 'Shipping:', 'woocommerce' ); ?></td>
				<td width="1%"></td>
				<td class="total">
					<?php
					$captured = false;
					if ( $captured > 0 ) {
						echo '<del>' . wp_kses_post( wc_price( $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ) ) . '</del> <ins>' . wp_kses_post( wc_price( $order->get_shipping_total() - $captured, array( 'currency' => $order->get_currency() ) ) ) . '</ins>';
					} else {
						echo wp_kses_post( wc_price( $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ) );
					}
					?>
				</td>
			</tr>
		<?php endif; ?>

		<?php do_action( 'woocommerce_admin_order_totals_after_shipping', $order->get_id() ); ?>

		<?php if ( wc_tax_enabled() ) : ?>
			<?php foreach ( $order->get_tax_totals() as $code => $tax_total ) : ?>
				<tr>
					<td class="label"><?php echo esc_html( $tax_total->label ); ?>:</td>
					<td width="1%"></td>
					<td class="total">
						<?php
						$captured = false;
						if ( $captured > 0 ) {
							echo '<del>' . wp_kses_post( $tax_total->formatted_amount ) . '</del> <ins>' . wp_kses_post( wc_price( roundAmount( $tax_total->amount ) - roundAmount( $captured ), array( 'currency' => $order->get_currency() ) ) ) . '</ins>';
						} else {
							echo wp_kses_post( $tax_total->formatted_amount );
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_admin_order_totals_after_tax', $order->get_id() ); ?>

		<tr>
			<td class="label"><?php esc_html_e( 'Total', 'woocommerce' ); ?>:</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
			</td>
		</tr>

		<?php do_action( 'woocommerce_admin_order_totals_after_total', $order->get_id() ); ?>

		<?php if ( isset( $amountAlreadyCaptured ) && $amountAlreadyCaptured ) : ?>
			<tr>
				<td class="label captured-total"><?php esc_html_e( 'Captured', 'woocommerce' ); ?>:</td>
				<td width="1%"></td>
				<td class="total captured-total">-<?php echo wp_kses_post( wc_price( $amountAlreadyCaptured, array( 'currency' => $order->get_currency() ) ) ); ?></td>
			</tr>
		<?php endif; ?>

		<?php do_action( 'woocommerce_admin_order_totals_after_captured', $order->get_id() ); ?>

	</table>
	<div class="clear"></div>
</div>
<div class="wc-order-data-row wc-capture-bulk-actions wc-order-capture-data-row-toggle">
	<p class="add-items">
		<?php
		if ( ! isset( $amountAlreadyCaptured ) ) {
			$amountAlreadyCaptured = 0;
		}
		if ( 0 < $order->get_total() - $amountAlreadyCaptured || 0 < absint( $order->get_item_count() - $order->get_item_count_captured() ) ) :
			?>
			<button type="button" class="button capture-items">Capture</button>
		<?php endif; ?>
		<?php
			// Allow adding custom buttons.
			do_action( 'woocommerce_order_item_add_action_buttons', $order );
		?>

	</p>
</div>
<div class="wc-order-data-row wc-order-add-item wc-order-capture-data-row-toggle" style="display:none;">
	<button type="button" class="button add-order-item"><?php esc_html_e( 'Add product(s)', 'woocommerce' ); ?></button>
	<button type="button" class="button add-order-fee"><?php esc_html_e( 'Add fee', 'woocommerce' ); ?></button>
	<button type="button" class="button add-order-shipping"><?php esc_html_e( 'Add shipping', 'woocommerce' ); ?></button>
	<?php if ( wc_tax_enabled() ) : ?>
		<button type="button" class="button add-order-tax"><?php esc_html_e( 'Add tax', 'woocommerce' ); ?></button>
	<?php endif; ?>
	<?php
		// Allow adding custom buttons.
		do_action( 'woocommerce_order_item_add_line_buttons', $order );
	?>
	<button type="button" class="button cancel-action"><?php esc_html_e( 'Cancel', 'woocommerce' ); ?></button>
	<button type="button" class="button button-primary save-action"><?php esc_html_e( 'Save', 'woocommerce' ); ?></button>
</div>
<?php

$amountAlreadyCaptured = get_post_meta( $order->get_id(), '_wc_order_amount_captured', true ) ? (float) str_replace( ',', '.', get_post_meta( $order->get_id(), '_wc_order_amount_captured', true ) ) : 0;

if ( $order->get_total() - $amountAlreadyCaptured > 0 ) :

	?>
<div class="wc-order-data-row wc-order-capture-items wc-order-capture-data-row-toggle" style="display: none;">
	<table class="wc-order-totals capture-totals">
		<tr>
	
			<td class="label"><?php esc_html_e( 'Amount already captured', 'woocommerce' ); ?>:</td>
			<td class="total"><?php echo wp_kses_post( wc_price( $amountAlreadyCaptured, array( 'currency' => $order->get_currency() ) ) ); ?></td>
		</tr>
		<tr>
		
			<td class="label"><?php esc_html_e( 'Total available to capture', 'woocommerce' ); ?>:</td>
			<td class="total"><?php echo wp_kses_post( wc_price( $order->get_total() - $amountAlreadyCaptured, array( 'currency' => $order->get_currency() ) ) ); ?></td>
		</tr>
		<tr>
	
			<td class="label">
				<label for="capture_amount">
					<?php echo wp_kses_post( wc_help_tip( __( 'Capture the line items above. This will show the total amount to be captured', 'woocommerce' ) ) ); ?>
					<?php esc_html_e( 'Capture amount', 'woocommerce' ); ?>:
				</label>
			</td>
			<td class="total">
				<input type="text" id="capture_amount" name="capture_amount" disabled="true" class="capture_input_price"/>
				<div class="clear"></div>
			</td>
		</tr>
	</table>
	<div class="clear"></div>
	<div class="capture-actions">
		<?php
		$capture_amount = '<span class="wc-order-capture-amount">' . wc_price( 0, array( 'currency' => $order->get_currency() ) ) . '</span>';
		$gateway_name   = false !== $payment_gateway ? ( ! empty( $payment_gateway->method_title ) ? $payment_gateway->method_title : $payment_gateway->get_title() ) : __( 'Payment gateway', 'woocommerce' );
		?>
		<?php /* translators: capture amount  */ ?>
		<button type="button" class="button button-primary do-manual-capture tips" ><?php printf( esc_html__( 'Capture %s', 'woocommerce' ), wp_kses_post( $capture_amount ) ); ?></button>
		<button type="button" class="button cancel-capture"><?php esc_html_e( 'Cancel', 'woocommerce' ); ?></button>
		<input type="hidden" id="captured_amount" name="captured_amount" value="<?php echo esc_attr( $amountAlreadyCaptured ); ?>" />
		<div class="clear"></div>
	</div>
	<div class="clear"></div>
</div>
<?php endif; ?>

<script type="text/template" id="tmpl-wc-modal-add-products">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Add products', 'woocommerce' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text">Close modal panel</span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<table class="widefat">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
								</tr>
							</thead>
							<?php
								$row = '
									<td><select class="wc-product-search" name="item_id" data-allow_clear="true" data-display_stock="true" data-placeholder="' . esc_attr__( 'Search for a product&hellip;', 'woocommerce' ) . '"></select></td>
									<td><input type="number" step="1" min="0" max="9999" autocomplete="off" name="item_qty" placeholder="1" size="4" class="quantity" /></td>';
							?>
							<tbody data-row="<?php echo esc_attr( $row ); ?>">
								<tr>
									<?php echo esc_html( $row ); ?>
								</tr>
							</tbody>
						</table>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add', 'woocommerce' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>

<script type="text/template" id="tmpl-wc-modal-add-tax">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Add tax', 'woocommerce' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text">Close modal panel</span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<table class="widefat">
							<thead>
								<tr>
									<th>&nbsp;</th>
									<th><?php esc_html_e( 'Rate name', 'woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Tax class', 'woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Rate code', 'woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Rate %', 'woocommerce' ); ?></th>
								</tr>
							</thead>
						<?php
							$rates = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_name LIMIT 100" );

						foreach ( $rates as $rate ) {
							echo '
									<tr>
										<td><input type="radio" id="add_order_tax_' . absint( $rate->tax_rate_id ) . '" name="add_order_tax" value="' . absint( $rate->tax_rate_id ) . '" /></td>
										<td><label for="add_order_tax_' . absint( $rate->tax_rate_id ) . '">' . esc_html( WC_Tax::get_rate_label( $rate ) ) . '</label></td>
										<td>' . ( isset( $classes_options[ $rate->tax_rate_class ] ) ? esc_html( $classes_options[ $rate->tax_rate_class ] ) : '-' ) . '</td>
										<td>' . esc_html( WC_Tax::get_rate_code( $rate ) ) . '</td>
										<td>' . esc_html( WC_Tax::get_rate_percent( $rate ) ) . '</td>
									</tr>
								';
						}
						?>
						</table>
						<?php if ( absint( $wpdb->get_var( "SELECT COUNT(tax_rate_id) FROM {$wpdb->prefix}woocommerce_tax_rates;" ) ) > 100 ) : ?>
							<p>
								<label for="manual_tax_rate_id"><?php esc_html_e( 'Or, enter tax rate ID:', 'woocommerce' ); ?></label><br/>
								<input type="number" name="manual_tax_rate_id" id="manual_tax_rate_id" step="1" placeholder="<?php esc_attr_e( 'Optional', 'woocommerce' ); ?>" />
							</p>
						<?php endif; ?>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add', 'woocommerce' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
