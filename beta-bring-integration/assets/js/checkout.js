/**
 * Bring Shipping – checkout / cart enhancements.
 *
 * 1. Makes the shipping table row span the full table width so the
 *    options have more room to breathe.
 * 2. Styles each shipping option as a selectable card and highlights
 *    the selected one. The CSS `:has()` rule handles this in modern
 *    browsers; the `.bbi-selected` class added here is the fallback
 *    for browsers that don't support `:has()`.
 * 3. Shows the enriched details panel (logo, delivery estimate,
 *    description, closest pickup point) only for the selected option.
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
	 * Sync the .bbi-selected class on <li> items to reflect which
	 * shipping radio is checked.  This drives the card highlight and
	 * detail-panel visibility for browsers that lack :has() support.
	 */
	function syncSelectedCard() {
		var $lists = $( 'ul.woocommerce-shipping-rates' );
		if ( ! $lists.length ) {
			return;
		}

		$lists.each( function () {
			var $ul = $( this );
			$ul.children( 'li' ).removeClass( 'bbi-selected' );
			$ul.find( 'input[name^="shipping_method"]:checked' ).closest( 'li' ).addClass( 'bbi-selected' );
		} );
	}

	function bindShippingChange() {
		$( document ).on( 'change', 'input[name^="shipping_method"]', function () {
			syncSelectedCard();
		} );
	}

	$( function () {
		expandShippingRow();
		syncSelectedCard();
		bindShippingChange();
	} );

	// Re-apply after WooCommerce fragment / AJAX updates.
	$( document.body ).on( 'updated_cart_totals updated_checkout wc_fragments_refreshed', function () {
		expandShippingRow();
		syncSelectedCard();
	} );
} )( jQuery );
