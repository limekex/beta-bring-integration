/**
 * Bring Shipping – checkout / cart enhancements.
 *
 * 1. Makes the shipping table row span the full table width so the
 *    options have more room to breathe.
 * 2. Shows the enriched details panel (logo, delivery estimate, description,
 *    closest pickup point) only for the currently selected shipping option,
 *    and updates the visible panel when the selection changes.
 *
 * The CSS `:checked + label .bbi-shipping-details` rule already handles the
 * initial state and post-AJAX refresh; the JS change handler gives instant
 * feedback before the browser re-renders the pseudo-class.
 */
( function ( $ ) {
	'use strict';

	/**
	 * Expand the shipping <tr> so it spans the full table width.
	 *
	 * WooCommerce renders shipping as a 2-column table row:
	 *   <th>Shipping</th> <td>…options…</td>
	 *
	 * We move the heading text into the <td>, hide the <th>, and set
	 * colspan="2" on the <td> so the options have the full row width.
	 */
	function expandShippingRow() {
		$( 'tr.shipping, tr.woocommerce-shipping-totals' ).each( function () {
			var $tr = $( this );
			var $th = $tr.children( 'th' );
			var $td = $tr.children( 'td' );

			// Skip if already processed or no standard 2-cell structure.
			if ( ! $th.length || ! $td.length || $td.hasClass( 'bbi-shipping-expanded' ) ) {
				return;
			}

			// Prepend the heading text as an in-cell label.
			var headingText = $th.text().trim();
			if ( headingText ) {
				$td.prepend( $( '<span class="bbi-shipping-row-label"></span>' ).text( headingText ) );
			}

			// Widen the cell and hide the now-redundant heading cell.
			$td.addClass( 'bbi-shipping-expanded' ).attr( 'colspan', 2 );
			$th.addClass( 'bbi-shipping-th-hidden' );
		} );
	}

	/**
	 * Show/hide details panels in response to a radio-button change.
	 * The CSS :checked rule handles the visual state; this handler
	 * just forces an instant repaint before CSS can catch up.
	 */
	function bindShippingChange() {
		$( document ).on( 'change', 'input[name^="shipping_method"]', function () {
			var $ul = $( this ).closest( 'ul' );
			$ul.find( '.bbi-shipping-details' ).hide();
			$ul.find( 'input[name^="shipping_method"]:checked + label .bbi-shipping-details' ).show();
		} );
	}

	$( function () {
		expandShippingRow();
		bindShippingChange();
	} );

	// Re-apply after WooCommerce fragment / AJAX updates.
	$( document.body ).on( 'updated_cart_totals updated_checkout wc_fragments_refreshed', function () {
		expandShippingRow();
	} );
} )( jQuery );
