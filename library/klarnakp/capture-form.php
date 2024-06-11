<?php
$previous_captures    = $order_capture->get_previous_captures();
$items_to_capture     = $this->get_available_to_capture_by_type( $order_capture );
$refunded_capture_ids = $this->get_refunded_captures( $order->get_id() );
?>

<style>
	.bk-previous-capture,
	.bk-to-capture {
		display: flex;
		justify-content: space-between;
		border-bottom: 1px solid #ccc;
		padding: 5px;
		align-items: center;
	}

	.bk-to-capture .bk-auto {
		flex: 0 0 auto;
		min-width: 5%;
		text-align: right;

	}

	.bk-to-capture .name {
		flex: 1 0 0%;
	}

	.bk-to-capture .m-h-15 {
		margin: 0 10px;
	}

	.bk-to-capture .fee {
		position: relative;
		padding-left: 17px;
	}

	.bk-to-capture .fee:before {
		font-family: WooCommerce;
		speak: never;
		font-weight: 400;
		font-variant: normal;
		text-transform: none;
		margin: 0;
		text-indent: 0;
		position: absolute;
		top: 0;
		left: 0;
		text-align: center;
		content: "\e007";
		color: #ccc;
	}

	.bk-to-capture .shipping {
		position: relative;
		padding-left: 17px;
	}

	.bk-to-capture .qty-max {
		min-width: 2%;
	}

	.bk-to-capture .shipping:before {
		font-family: WooCommerce;
		speak: never;
		font-weight: 400;
		font-variant: normal;
		text-transform: none;
		margin: 0;
		text-indent: 0;
		position: absolute;
		top: 0;
		left: 0;
		text-align: center;
		content: "\e01a";
		color: #ccc;
	}

	.bk-to-capture-head {
		background-color: #f8f8f8;
		color: #999;
		padding: 15px 5px;
	}

	.bk-to-capture-head .bk-auto {
		text-align: left;
	}

	.bk-capture-action {
		display: flex;
		justify-content: end;
		align-items: center;
	}

	.bk-refund-amount-wrap {
		display: flex;
		align-items: center;
	}

	.bk-refund-amount-wrap .bk-refund-btn-wrap {
		margin-left: 10px;
	}
	.bk-refund-form {
		display: flex;
		justify-content: end;
	}
	.bk-refund-left {
		display: flex;
		flex-direction: column;
		justify-content: end;
	}
	.bk-refund-restock,
	.bk-refund-reason {
		display: flex;
		justify-content: space-between;
		margin-top: 5px;
		align-items: center;
	}
	.bk-refund-button-wrap {
		display: flex;
		justify-content: end;
		margin-top: 5px;
	}
</style>
<script>
	jQuery(function($) {
		calculateTotal();
		$('.bk-to-capture input[data-item-id]').on('input', function() {
			calculateTotal();
		});

		$('.capture-bk-items').on('click', function() {
			if (confirm('Are you sure you wish to process this capture? This action cannot be undone.')) {

				$( '#buckaroo-order-klarnakp-capture' ).block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				$(this).prop('disabled', true);
				const line_item_qtys = get_item_qtys();
				const line_item_totals = get_item_totals();
				const line_item_tax_totals = {};
				const captured_amount = 0;
				var data = {
					action: 'order_capture',
					order_id: woocommerce_admin_meta_boxes.post_id,
					capture_amount: $('#bk-capture-total').val(),
					captured_amount: captured_amount,
					line_item_qtys: JSON.stringify(line_item_qtys, null, ''),
					line_item_totals: JSON.stringify(line_item_totals, null, ''),
					line_item_tax_totals: JSON.stringify(line_item_tax_totals, null, ''),
					api_refund: false,
					security: woocommerce_admin_meta_boxes.order_item_nonce
				};
				$.post(ajaxurl, data, function(response) {
					$('.capture-bk-items').prop('disabled', false);
					$( '#buckaroo-order-klarnakp-capture' ).unblock();
					if (true === response.success) {
						// Redirect to same page for show the refunded status
						window.location.reload();
					} else {
						if (response.errors && response.errors.error_capture) {
							window.alert(response.errors.error_capture[0]);
						}
					}
				});
			}

		});
		$('.bk-btn-refund-form').on('click', function() {
			$(this).closest('li').next('.bk-refund-form').slideToggle()
		})
		$('.bk-btn-refund').on('click', function() {
			if (confirm('Are you sure you wish to process this REFUND? This action cannot be undone.')) {
				$( '#buckaroo-order-klarnakp-capture' ).block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				const btn = $(this);
				$(this).prop('disabled', true);
				const form = $(this).closest('.bk-refund-form');
				var data = {
					action: 'bl_refund_klarnakp_capture',
					capture_id: $(this).attr('data-capture-id'),
					order_id: woocommerce_admin_meta_boxes.post_id,
					reason: form.find('.bk-refund-reason input').val(),
					restock: form.find('.bk-refund-restock input').is(':checked')
				}

				$.post(ajaxurl, data, function(response) {
					$( '#buckaroo-order-klarnakp-capture' ).unblock();
					btn.prop('disabled', false);
					if (response.error) {
						window.alert(response.error);
					} else {
						window.location.reload();
					}
				});
			}
		})

		function get_item_totals() {
			totals = {};
			$('input[data-item-id]').each(function() {
				const id = $(this).attr('data-item-id');
				let type = $(this).attr('type');
				let unit_price = $(this).attr('data-unit-price');
				let qty = $(this).val();
				if (type == 'number' && qty > 0) {
					totals[id] = Math.round((qty * parseFloat(unit_price)) * 100) / 100;
				}

				if (type == 'checkbox' && $(this).is(':checked')) {
					totals[id] = Math.round(parseFloat(unit_price) * 100) / 100;
				}
			})
			return totals;
		}

		function get_item_qtys() {
			let qtys = {};
			$('input[data-item-id][type="number"]').each(function() {
				const id = $(this).attr('data-item-id');
				const qty = $(this).val();
				if (qty > 0) {
					qtys[id] = qty;
				}
			})
			return qtys;
		}

		function calculateTotal() {
			let total = 0;
			$('.bk-to-capture input[data-item-id]').each(function() {
				let type = $(this).attr('type');
				let unit_price = $(this).attr('data-unit-price');
				let qty = $(this).val();
				if (type == 'number' && qty > 0) {
					total += qty * parseFloat(unit_price);
				}

				if (type == 'checkbox' && $(this).is(':checked')) {
					total += parseFloat(unit_price);
				}
			})
			$('#bk-capture-total').val(Math.round(total * 100) / 100);
		}
	})
</script>
<?php
if ( count( $items_to_capture ) ) {
	?>
	<div class="bk-to-capture bk-to-capture-head">
		<div class="name">
			<?php esc_html_e( 'Item', 'woocommerce' ); ?>
		</div>
		<div class="cost bk-auto m-h-15">
			<?php esc_html_e( 'Cost', 'woocommerce' ); ?>
		</div>
		<div class="qty-max bk-auto m-h-15">
			<?php esc_html_e( 'Qty', 'woocommerce' ); ?>
		</div>
		<div class="qty bk-auto m-h-15">
			<?php esc_html_e( 'Capture', 'woocommerce' ); ?>
		</div>
		<div class="amount bk-auto m-h-15" style="margin-right:0">
			<?php esc_html_e( 'Total', 'woocommerce' ); ?>
		</div>
	</div>

	<?php
}
/**
 *  product items
 */
if ( isset( $items_to_capture['line_item'] ) ) {
	?>
	<ul>

		<?php
		foreach ( $items_to_capture['line_item'] as $item ) {
			?>
			<li class="bk-to-capture">
				<div class="name">
					<?php echo esc_html( $item->get_title() ); ?>
				</div>
				<div class="cost bk-auto m-h-15">
					<?php
					echo wc_price( $item->get_unit_price(), array( 'currency' => $order_capture->get_order_details()->get_currency() ) )
					?>
				</div>
				<div class="qty-max bk-auto m-h-15">
					× <?php echo $item->get_quantity(); ?>
				</div>
				<div class="qty bk-auto m-h-15">
					<input data-item-id="<?php echo $item->get_line_item_id(); ?>" data-unit-price="<?php echo $item->get_unit_price(); ?>" type="number" step="1" min="0" max="<?php echo $item->get_quantity(); ?>" value="<?php echo $item->get_quantity(); ?>">
				</div>
				<div class="amount bk-auto m-h-15" style="margin-right:0">
					<?php
					echo wc_price( $item->get_total_amount(), array( 'currency' => $order_capture->get_order_details()->get_currency() ) )
					?>
				</div>
			</li>
			<?php
		}
		?>
	</ul>

	<?php
}

/**
 *  shipping items
 */
if ( isset( $items_to_capture['shipping'] ) ) {
	?>
	<ul>

		<?php
		foreach ( $items_to_capture['shipping'] as $item ) {
			?>
			<li class="bk-to-capture">
				<div class="name shipping">
					<?php echo esc_html( $item->get_title() ); ?>
				</div>
				<div class="qty bk-auto m-h-15">
					<input data-item-id="<?php echo $item->get_line_item_id(); ?>" data-unit-price="<?php echo $item->get_unit_price(); ?>" type="checkbox" checked="checked">
				</div>
				<div class="amount bk-auto m-h-15" style="margin-right:0">
					<?php
					echo wc_price( $item->get_unit_price(), array( 'currency' => $order_capture->get_order_details()->get_currency() ) )
					?>
				</div>
			</li>
			<?php
		}
		?>
	</ul>

	<?php
}

/**
 *  fee items
 */
if ( isset( $items_to_capture['fee'] ) ) {
	?>
	<ul>

		<?php
		foreach ( $items_to_capture['fee'] as $item ) {
			?>
			<li class="bk-to-capture">
				<div class="name fee">
					<?php echo esc_html( $item->get_title() ); ?>
				</div>
				<div class="qty bk-auto m-h-15">
					<input data-item-id="<?php echo $item->get_line_item_id(); ?>" data-unit-price="<?php echo $item->get_unit_price(); ?>" type="checkbox" checked="checked">
				</div>
				<div class="amount bk-auto m-h-15" style="margin-right:0">
					<?php
					echo wc_price( $item->get_unit_price(), array( 'currency' => $order_capture->get_order_details()->get_currency() ) )
					?>
				</div>
			</li>
			<?php
		}
		?>
	</ul>

	<?php
}

if ( count( $previous_captures ) ) {
	echo esc_html__( 'Previous capture(s)', 'woocommerce' );
	?>
	<ul>
		<?php
		foreach ( $previous_captures as $capture ) {
			?>
			<li class="bk-previous-capture">
				<div class="name">
					<?php
					printf(
						esc_html__( 'Capture - #%s', 'woocommerce' ),
						esc_attr( $capture->get_id() )
					);
					?>
				</div>
				<div class="bk-refund-amount-wrap">
					<div class="amount">
						<?php
						echo wc_price( str_replace( ',', '.', $capture->get_total_amount() ), array( 'currency' => $capture->get_currency() ) )
						?>
					</div>
					<?php
					if ( ! in_array( $capture->get_id(), $refunded_capture_ids ) ) {
						?>
						<div class="bk-auto bk-refund-btn-wrap">
							<button type="button" class="bk-btn-refund-form button">
								<?php echo __( 'Refund', 'woocommerce' ); ?>
							</button>
						</div>
						<?php
					}
					?>
				</div>

			</li>
			<?php
			if ( ! in_array( $capture->get_id(), $refunded_capture_ids ) ) {
				?>
				<li class="bk-refund-form" style="display:none;">
					<div class="bk-refund-left">
						<label class="bk-refund-restock" for="bk-refund-restock<?php echo $capture->get_id(); ?>">
							<?php esc_html_e( 'Restock refunded items', 'woocommerce' ); ?>:
							<input type="checkbox" id="bk-refund-restock<?php echo $capture->get_id(); ?>" checked="checked">
						</label>
						<label class="bk-refund-reason" for="bk-refund-reason<?php echo $capture->get_id(); ?>">
							<?php esc_html_e( 'Reason for refund (optional):', 'woocommerce' ); ?>
							<input type="text" id="bk-refund-reason<?php echo $capture->get_id(); ?>" style="margin-left:5px;">
						</label>
						<div class="bk-auto bk-refund-button-wrap">
							<button type="button" class="bk-btn-refund button button-primary" data-capture-id="<?php echo $capture->get_id(); ?>">
								<?php echo __( 'Refund', 'woocommerce' ); ?>
							</button>
						</div>
					</div>
				</li>
				<?php
			}
			?>
			<?php
		}
		?>
	</ul>
	<?php
}
if ( count( $items_to_capture ) ) {
	?>

	<div class="bk-capture-action">
		<?php esc_html_e( 'Total available to capture', 'woocommerce' ); ?>:
		<input type="text" name="total" disabled id="bk-capture-total" style="text-align:right;">
		<p class="add-items">
			<button type="button" class="button button-primary capture-bk-items">Capture</button>
		</p>
	</div>
	<?php
}
