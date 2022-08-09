jQuery(document).ready(function() {

    jQuery("button.capture-items").on('click', function (e) {
        captureForm();
        inputChanged();
    });

    jQuery("button.cancel-capture").on('click', function (e) {
        cancelForm();
    });

    jQuery("button.do-manual-capture").on('click', function (e) {
        doCapture();
    });

    jQuery(".capture input.capture_line_total, .capture input.capture_line_tax").on('change', function (e) {
        inputChanged();
    });

    jQuery(".wc-order-capture-items #capture_amount").on('change', function (e) {
        amountChanged();
    });


    var doCapture = function() {

        if ( window.confirm( 'Are you sure you wish to process this capture? This action cannot be undone.' ) ) {
            var capture_amount   = jQuery( 'input#capture_amount' ).val();
            var captured_amount = jQuery( 'input#captured_amount' ).val();

            // Get line item refunds
            var line_item_qtys       = {};
            var line_item_totals     = {};
            var line_item_tax_totals = {};

            jQuery( '.capture input.capture_order_item_qty' ).each(function( index, item ) {
                if ( jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ) {
                    if ( item.value ) {
                        line_item_qtys[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ] = item.value;
                    }
                }
            });

            jQuery( '.capture input.capture_line_total' ).each(function( index, item ) {
                if ( jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ) {
                    line_item_totals[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ] = item.value;
                }
            });

            jQuery( '.capture input.capture_line_tax' ).each(function( index, item ) {
                if ( jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ) {
                    var tax_id = jQuery( item ).data( 'tax_id' );

                    if ( ! line_item_tax_totals[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ] ) {
                        line_item_tax_totals[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ] = {};
                    }

                    line_item_tax_totals[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ][ tax_id ] = item.value;
                }
            });
            
            var data = {
                action                : 'order_capture',
                order_id              : woocommerce_admin_meta_boxes.post_id,
                capture_amount        : capture_amount,
                captured_amount       : captured_amount,
                line_item_qtys        : JSON.stringify( line_item_qtys, null, '' ),
                line_item_totals      : JSON.stringify( line_item_totals, null, '' ),
                line_item_tax_totals  : JSON.stringify( line_item_tax_totals, null, '' ),
                api_refund            : jQuery( this ).is( '.do-api-refund' ),
                security              : woocommerce_admin_meta_boxes.order_item_nonce
            };

            jQuery.post( ajaxurl, data, function( response ) {

                if ( true === response.success ) {
                    // Redirect to same page for show the refunded status
                    window.location.reload();
                } else {
                    if (response.errors && response.errors.error_capture) {
                        window.alert(response.errors.error_capture[0]);
                    }
                }
            });
        }
    }


    jQuery("input.capture_order_item_qty").on('change', function (e) {

        function BK_price_round(num) {
            return +(Math.round(num + "e+2")  + "e-2");
        }

        var $row              = jQuery( this ).closest( 'tr.capture-item ' );
        var qty               = $row.find( 'input.quantity' ).val();
        var refund_qty        = jQuery( this ).val();
        var line_total        = jQuery( 'input.line_total', $row );
        var refund_line_total = jQuery( 'input.capture_line_total', $row );

        // Totals
        var unit_total = accounting.unformat( line_total.attr( 'data-total' ), woocommerce_admin.mon_decimal_point ) / qty;

        refund_line_total.val(
            parseFloat( accounting.formatNumber( unit_total * refund_qty, woocommerce_admin_meta_boxes.rounding_precision, '' ) )
                .toString()
                .replace( '.', woocommerce_admin.mon_decimal_point )
        ).change();

        // Taxes
        jQuery( '.capture_line_tax', $row ).each( function() {
            var $refund_line_total_tax = jQuery( this );
            var tax_id                 = $refund_line_total_tax.data( 'tax_id' );
            var line_total_tax         = jQuery( 'input.line_tax[data-tax_id="' + tax_id + '"]', $row );
            var unit_total_tax         = accounting.unformat( line_total_tax.data( 'total_tax' ), woocommerce_admin.mon_decimal_point ) / qty;

            if ( 0 < unit_total_tax ) {
                $refund_line_total_tax.val(
                    parseFloat( accounting.formatNumber( BK_price_round(unit_total_tax * refund_qty), woocommerce_admin_meta_boxes.rounding_precision, '' ) )
                        .toString()
                        .replace( '.', woocommerce_admin.mon_decimal_point )
                ).change();
            } else {
                $refund_line_total_tax.val( 0 ).change();
            }
        });

    });


    var inputChanged = function() {
        var capture_amount = 0;
        var $items        = jQuery( '.woocommerce_order_items' ).find( 'tr.capture-item, tr.fee, tr.shipping' );

        $items.each(function() {
            var $row               = jQuery( this );
            var refund_cost_fields = $row.find( '.capture input:not(.capture_order_item_qty)' );

            refund_cost_fields.each(function( index, el ) {
                capture_amount += parseFloat( jQuery( el ).val().replace(',','.') || 0 );
            });
        });

        jQuery( '#capture_amount' )
            .val( accounting.formatNumber(
                capture_amount,
                woocommerce_admin_meta_boxes.currency_format_num_decimals,
                '',
                woocommerce_admin.mon_decimal_point
            ) )
            .change();
    }


	var amountChanged = function() {
				var total = accounting.unformat( jQuery(".wc-order-capture-items #capture_amount").val(), woocommerce_admin.mon_decimal_point );
				jQuery( 'button .wc-order-capture-amount .amount' ).text( accounting.formatMoney( total, {
					symbol:    woocommerce_admin_meta_boxes.currency_format_symbol,
					decimal:   woocommerce_admin_meta_boxes.currency_format_decimal_sep,
					thousand:  woocommerce_admin_meta_boxes.currency_format_thousand_sep,
					precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
					format:    woocommerce_admin_meta_boxes.currency_format
				} ) );
			}


    var captureForm = function() {
        jQuery( 'div.wc-order-capture-items' ).slideDown();
        jQuery( 'div.wc-order-capture-data-row-toggle' ).not( 'div.wc-order-capture-items' ).slideUp();
        jQuery( 'div.wc-order-capture-totals-items' ).slideUp();
        jQuery( '#buckaroo-order-capture' ).find( 'div.capture' ).show();
        jQuery( '.wc-order-capture-edit-line-item .wc-order-edit-line-item-actions' ).hide();
        return false;
    }

    var cancelForm = function() {
        jQuery( 'div.wc-order-capture-data-row-toggle' ).not( 'div.wc-capture-bulk-actions' ).slideUp();
        jQuery( 'div.wc-capture-bulk-actions' ).slideDown();
        jQuery( 'div.wc-order-capture-totals-items' ).slideDown();
        jQuery( '#buckaroo-order-capture' ).find( 'div.capture' ).hide();
        jQuery( '.wc-order-capture-edit-line-item .wc-order-edit-line-item-actions' ).show();

		return false;
    }




});

function doCall(id) {
    jQuery.ajax({
        type: "POST",
        url: ajaxurl,

        data: {
            id: id,
            action: "capture_lightbox"
        },
        success: function(data, textStatus, XMLHttpRequest) {        
            jQuery(".form-capture-" + data).show();
        },
        dataType: "json"
    }).done(function(data) {
        if ( console && console.log ) {
          console.log(data);
          

        }
      });

    return false;
}