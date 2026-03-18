/**
 * Bring Shipping – checkout / cart accordion card selector.
 *
 * 1. Makes the shipping table row span the full table width.
 * 2. Each option is a full-width clickable card — clicking anywhere
 *    on the card selects the underlying radio button.
 * 3. CSS `:has()` drives the selected highlight in modern browsers;
 *    `.bbi-selected` is the JS fallback.
 * 4. The accordion details panel expands only for the selected option.
 * 5. If the selected service requires a pickup point, a dropdown is
 *    populated from the REST API and the choice is stored in a hidden field.
 */
( function ( $ ) {
	'use strict';

	/** Track last-fetched postal code to avoid duplicate requests. */
	var lastPickupRequest = '';

	/**
	 * Expand the shipping <tr> to span the full table width.
	 */
	function expandShippingRow() {
		$( 'tr.shipping, tr.woocommerce-shipping-totals' ).each( function () {
			var $tr = $( this );
			var $th = $tr.children( 'th' );
			var $td = $tr.children( 'td' );

			if ( ! $th.length || ! $td.length || $td.hasClass( 'bbi-shipping-expanded' ) ) {
				return;
			}

			var headingText = $th.text().trim();
			if ( headingText ) {
				$td.prepend( $( '<span class="bbi-shipping-row-label"></span>' ).text( headingText ) );
			}

			$td.addClass( 'bbi-shipping-expanded' ).attr( 'colspan', 2 );
			$th.addClass( 'bbi-shipping-th-hidden' );
		} );
	}

	/**
	 * Get all shipping method list containers
	 * (WC uses #shipping_method / .woocommerce-shipping-methods / .woocommerce-shipping-rates).
	 */
	function getShippingLists() {
		return $( '#shipping_method, ul.woocommerce-shipping-methods, ul.woocommerce-shipping-rates' );
	}

	/**
	 * Sync the .bbi-selected class on <li> items.
	 */
	function syncSelectedCard() {
		var $lists = getShippingLists();
		if ( ! $lists.length ) {
			return;
		}

		$lists.each( function () {
			var $ul = $( this );
			$ul.children( 'li' ).removeClass( 'bbi-selected' );
			$ul.find( 'input[type="radio"]:checked, input[name^="shipping_method"]:checked' ).closest( 'li' ).addClass( 'bbi-selected' );
		} );

		loadPickupPoints();
	}

	/**
	 * Force-hide radio inputs AND theme-drawn pseudo-element radios via inline styles.
	 */
	function nukeRadios() {
		getShippingLists().find( 'input[type="radio"]' ).each( function () {
			this.style.setProperty( 'position', 'absolute', 'important' );
			this.style.setProperty( 'width', '0', 'important' );
			this.style.setProperty( 'height', '0', 'important' );
			this.style.setProperty( 'opacity', '0', 'important' );
			this.style.setProperty( 'pointer-events', 'none', 'important' );
			this.style.setProperty( 'overflow', 'hidden', 'important' );
			this.style.setProperty( 'clip', 'rect(0,0,0,0)', 'important' );
		} );

		if ( ! document.getElementById( 'bbi-nuke-label-radios' ) ) {
			var style = document.createElement( 'style' );
			style.id = 'bbi-nuke-label-radios';
			style.textContent =
				'#shipping_method li label::before,' +
				'#shipping_method li label::after,' +
				'.woocommerce-shipping-methods li label::before,' +
				'.woocommerce-shipping-methods li label::after,' +
				'.woocommerce-shipping-rates li label::before,' +
				'.woocommerce-shipping-rates li label::after{' +
				'display:none!important;content:none!important;' +
				'width:0!important;height:0!important;' +
				'background:none!important;border:none!important;' +
				'box-shadow:none!important}';
			document.head.appendChild( style );
		}
	}

	/**
	 * Make entire <li> clickable — selecting the radio inside.
	 */
	function bindCardClick() {
		$( document ).on( 'click', '#shipping_method li, ul.woocommerce-shipping-methods li, ul.woocommerce-shipping-rates li', function ( e ) {
			if ( $( e.target ).is( 'a, input, select, textarea, option' ) || $( e.target ).closest( 'select, .bbi-pickup-selector' ).length ) {
				return;
			}

			var $radio = $( this ).find( 'input[type="radio"]' );
			if ( $radio.length && ! $radio.prop( 'checked' ) ) {
				$radio.prop( 'checked', true ).trigger( 'change' );
			}
		} );
	}

	function bindShippingChange() {
		$( document ).on( 'change', '#shipping_method input[type="radio"], input[name^="shipping_method"]', function () {
			syncSelectedCard();
		} );
	}

	/* ── Pickup point selector logic ────────────────────────────────────── */

	/**
	 * Ensure the hidden input for pickup point ID exists in the checkout form.
	 */
	function ensureHiddenField() {
		if ( ! $( 'input[name="bbi_pickup_point_id"]' ).length ) {
			$( 'form.checkout, form.woocommerce-checkout' ).append(
				'<input type="hidden" name="bbi_pickup_point_id" value="" />' +
				'<input type="hidden" name="bbi_pickup_point_name" value="" />'
			);
		}
	}

	/**
	 * Read the customer's billing/shipping postal code and country from the checkout fields.
	 */
	function getDestination() {
		var shipToDifferent = $( '#ship-to-different-address-checkbox' ).is( ':checked' );
		var prefix = shipToDifferent ? '#shipping_' : '#billing_';
		var postcode = $( prefix + 'postcode' ).val() || '';
		var country  = $( prefix + 'country' ).val() || 'NO';
		return { postcode: postcode.replace( /\s+/g, '' ), country: country.toUpperCase() };
	}

	/**
	 * Load pickup points for the selected card's pickup selector, if applicable.
	 */
	function loadPickupPoints() {
		var $selected = getShippingLists().find( 'li.bbi-selected, li:has(input:checked)' ).first();
		var $selector = $selected.find( '.bbi-pickup-selector' );

		if ( ! $selector.length ) {
			// Selected method doesn't need pickup — clear hidden field.
			$( 'input[name="bbi_pickup_point_id"]' ).val( '' );
			$( 'input[name="bbi_pickup_point_name"]' ).val( '' );
			return;
		}

		ensureHiddenField();

		var dest = getDestination();
		if ( ! dest.postcode || dest.postcode.length < 3 ) {
			return;
		}

		var requestKey = dest.country + ':' + dest.postcode;
		if ( requestKey === lastPickupRequest ) {
			return; // Already loaded for this destination.
		}
		lastPickupRequest = requestKey;

		var $select = $selector.find( '.bbi-pickup-select' );
		$select.html( '<option value="">' + ( window.bbi_checkout_i18n ? bbi_checkout_i18n.loading : 'Loading…' ) + '</option>' );
		$select.prop( 'disabled', true );

		var restUrl = ( window.bbi_checkout_pickup && bbi_checkout_pickup.rest_url )
			? bbi_checkout_pickup.rest_url
			: '/wp-json/bbi/v1';

		$.ajax( {
			url: restUrl + '/checkout/pickup-points/' + encodeURIComponent( dest.country ) + '/' + encodeURIComponent( dest.postcode ),
			method: 'GET',
			dataType: 'json',
			beforeSend: function ( xhr ) {
				if ( window.bbi_checkout_pickup && bbi_checkout_pickup.nonce ) {
					xhr.setRequestHeader( 'X-WP-Nonce', bbi_checkout_pickup.nonce );
				}
			},
			success: function ( data ) {
				var points = data.pickupPoints || [];
				var options = '';

				if ( ! points.length ) {
					options = '<option value="">' + ( window.bbi_checkout_i18n ? bbi_checkout_i18n.no_pickup : 'No pickup points found' ) + '</option>';
				} else {
					options = '<option value="">' + ( window.bbi_checkout_i18n ? bbi_checkout_i18n.select_pickup : 'Select pickup point…' ) + '</option>';
					for ( var i = 0; i < points.length; i++ ) {
						options += '<option value="' + points[ i ].id + '" data-name="' + $( '<span>' ).text( points[ i ].name + ' – ' + points[ i ].address ).html() + '">'
							+ $( '<span>' ).text( points[ i ].name + ' – ' + points[ i ].address ).html()
							+ '</option>';
					}
				}

				$select.html( options ).prop( 'disabled', false );
			},
			error: function () {
				$select.html( '<option value="">' + ( window.bbi_checkout_i18n ? bbi_checkout_i18n.no_pickup : 'No pickup points found' ) + '</option>' );
				$select.prop( 'disabled', false );
			},
		} );
	}

	/**
	 * Sync the hidden field when the pickup dropdown changes.
	 */
	function bindPickupChange() {
		$( document ).on( 'change', '.bbi-pickup-select', function () {
			var $opt = $( this ).find( 'option:selected' );
			$( 'input[name="bbi_pickup_point_id"]' ).val( $( this ).val() );
			$( 'input[name="bbi_pickup_point_name"]' ).val( $opt.data( 'name' ) || $opt.text() );
		} );
	}

	/* ── Initialisation ─────────────────────────────────────────────────── */

	$( function () {
		expandShippingRow();
		nukeRadios();
		syncSelectedCard();
		bindCardClick();
		bindShippingChange();
		bindPickupChange();
	} );

	// Re-apply after WooCommerce fragment / AJAX updates.
	$( document.body ).on( 'updated_cart_totals updated_checkout wc_fragments_refreshed', function () {
		expandShippingRow();
		nukeRadios();
		lastPickupRequest = ''; // Reset so pickup is re-fetched with fresh DOM.
		syncSelectedCard();
	} );
} )( jQuery );
