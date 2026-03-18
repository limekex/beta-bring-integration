/**
 * BBI Bring – WooCommerce Blocks checkout enrichment.
 *
 * The WooCommerce Blocks checkout renders shipping options as React
 * components and does NOT fire the classic `woocommerce_cart_shipping_method_full_label`
 * filter.  This script bridges the gap:
 *
 * 1. Subscribes to the `wc/store/cart` Redux store (provided by WC Blocks) to
 *    build a map of  rate-ID → { gui_info, expected_delivery }  from the BBI
 *    Store API extension data registered in BlocksIntegration.php.
 *
 * 2. Uses a MutationObserver on document.body to detect whenever the Blocks
 *    checkout renders or refreshes shipping option rows, then injects the
 *    enriched HTML (logo, delivery estimate, description, pickup point hint)
 *    into each option element.
 *
 * 3. Toggles the `.bbi-selected` class on the option container in sync with
 *    the checked radio so the card-highlight CSS works in browsers that don't
 *    yet support `:has()`.
 *
 * All DOM mutations are idempotent (guarded by `data-bbi-enriched`).
 */
( function () {
	'use strict';

	/* ── Helpers ──────────────────────────────────────────────────── */

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * Build a flat map: { rateId → bbiData } from the WC Blocks Redux store.
	 * Returns an empty object if the store is not yet available.
	 */
	function buildRateMap() {
		var map = {};

		try {
			if ( ! window.wp || ! window.wp.data ) {
				return map;
			}

			var cartStore = wp.data.select( 'wc/store/cart' );
			if ( ! cartStore ) {
				return map;
			}

			// getCartData() is available in WC Blocks 10+.
			// Older versions expose getShippingRates() instead.
			var shippingRates;
			if ( typeof cartStore.getCartData === 'function' ) {
				var cartData = cartStore.getCartData();
				shippingRates = ( cartData && cartData.shipping_rates ) ? cartData.shipping_rates : [];
			} else if ( typeof cartStore.getShippingRates === 'function' ) {
				shippingRates = cartStore.getShippingRates() || [];
			} else {
				return map;
			}

			shippingRates.forEach( function ( pkg ) {
				var rates = pkg.shipping_rates || [];
				rates.forEach( function ( rate ) {
					var bbi = rate.extensions && rate.extensions.bbi;
					if ( bbi && rate.rate_id ) {
						map[ rate.rate_id ] = bbi;
					}
				} );
			} );
		} catch ( e ) {
			// Store not ready – silently return empty map.
		}

		return map;
	}

	/* ── DOM enrichment ────────────────────────────────────────────── */

	/**
	 * Inject the enriched Bring content (logo, delivery estimate, description,
	 * pickup point) into a single Blocks checkout shipping option element.
	 *
	 * @param {Element} container  The option container element (e.g. <li>).
	 * @param {Object}  bbiData    { gui_info, expected_delivery } from Store API.
	 */
	function enrichOption( container, bbiData ) {
		if ( container.dataset.bbiEnriched ) {
			return;
		}
		container.dataset.bbiEnriched = '1';

		var gui      = bbiData.gui_info || {};
		var delivery = bbiData.expected_delivery || {};
		var extra    = '';
		var i18n     = ( window.bbi_checkout && window.bbi_checkout.i18n ) ? window.bbi_checkout.i18n : {};

		// Logo.
		if ( gui.logoUrl ) {
			extra += '<img src="' + escHtml( gui.logoUrl ) + '" alt="' + escHtml( gui.logo || '' ) + '" class="bbi-shipping-logo" />';
		}

		// Delivery estimate.
		var deliveryDate = delivery.formattedExpectedDeliveryDate || '';
		var workingDays  = parseInt( delivery.workingDays || '0', 10 );

		if ( deliveryDate ) {
			var dText;
			var tpl;
			if ( workingDays === 1 ) {
				tpl   = i18n.expected_delivery_days_singular || 'Expected delivery %1$s (1 working day)';
				dText = tpl.replace( '%1$s', escHtml( deliveryDate ) );
			} else if ( workingDays > 1 ) {
				tpl   = i18n.expected_delivery_days_plural || 'Expected delivery %1$s (%2$d working days)';
				dText = tpl.replace( '%1$s', escHtml( deliveryDate ) ).replace( '%2$d', workingDays );
			} else {
				tpl   = i18n.expected_delivery_date || 'Expected delivery %s';
				dText = tpl.replace( '%s', escHtml( deliveryDate ) );
			}
			extra += '<span class="bbi-delivery-estimate">' + dText + '</span>';
		}

		// Description.
		if ( gui.descriptionText ) {
			extra += '<span class="bbi-shipping-desc">' + escHtml( gui.descriptionText ) + '</span>';
		}

		// Closest pickup point.
		if ( gui.closestPickupPoint ) {
			var pickupLabel = ( i18n.closest_pickup || 'Closest pickup point: ' );
			extra += '<span class="bbi-pickup-hint">' + escHtml( pickupLabel ) + escHtml( gui.closestPickupPoint ) + '</span>';
		}

		if ( ! extra ) {
			return;
		}

		var details = document.createElement( 'div' );
		details.className = 'bbi-shipping-details';
		details.innerHTML = extra;
		container.appendChild( details );
	}

	/**
	 * Find all rendered Blocks checkout shipping option containers and
	 * enrich/re-enrich them using the current rate map.
	 *
	 * WC Blocks renders each rate as a `<li>` inside the radio control list.
	 * The radio input's `name` follows `radio-control-wc-shipping-rates-*`
	 * and its `value` is the rate ID.
	 *
	 * @param {Object} rateMap  { rateId → bbiData }
	 */
	function enrichAll( rateMap ) {
		if ( ! Object.keys( rateMap ).length ) {
			return;
		}

		// Target <li> elements that contain a Blocks checkout shipping radio.
		// Using querySelectorAll on the input and walking to the closest <li>
		// keeps us independent of the exact class structure WC Blocks uses.
		// Array.prototype.slice converts NodeList for cross-browser safety.
		var inputs = Array.prototype.slice.call(
			document.querySelectorAll( 'input[name^="radio-control-wc-shipping-rates"]' )
		);

		inputs.forEach( function ( input ) {
			var rateId    = input.value;
			var bbiData   = rateMap[ rateId ];
			if ( ! bbiData ) {
				return;
			}

			// Walk up to the closest <li> or option wrapper.
			var container = input.closest( 'li' ) || input.parentElement;
			if ( container ) {
				enrichOption( container, bbiData );
			}
		} );
	}

	/* ── Selected-card sync ────────────────────────────────────────── */

	/**
	 * Toggle .bbi-selected on each Blocks checkout shipping option container
	 * to match the currently checked radio (CSS :has() fallback for older
	 * browsers).
	 */
	function syncSelectedBlocks() {
		var inputs = Array.prototype.slice.call(
			document.querySelectorAll( 'input[name^="radio-control-wc-shipping-rates"]' )
		);

		inputs.forEach( function ( input ) {
			var container = input.closest( 'li' ) || input.parentElement;
			if ( container ) {
				container.classList.toggle( 'bbi-selected', input.checked );
			}
		} );
	}

	/* ── Bootstrap ─────────────────────────────────────────────────── */

	function run() {
		var rateMap  = {};
		var lastJson = '';

		/**
		 * Subscribe to the WC Blocks Redux store so we rebuild the rate map
		 * whenever the cart (and therefore shipping rates) changes.
		 */
		if ( window.wp && window.wp.data ) {
			wp.data.subscribe( function () {
				var newMap  = buildRateMap();
				var newJson = JSON.stringify( newMap );
				if ( newJson !== lastJson ) {
					lastJson = newJson;
					rateMap  = newMap;
					// A rate map change means rates just loaded or changed –
					// reset enrichment flags and re-enrich the DOM.
					Array.prototype.slice.call(
						document.querySelectorAll( '[data-bbi-enriched]' )
					).forEach( function ( el ) {
						delete el.dataset.bbiEnriched;
						var old = el.querySelector( '.bbi-shipping-details' );
						if ( old ) {
							old.parentNode.removeChild( old );
						}
					} );
					enrichAll( rateMap );
					syncSelectedBlocks();
				}
			} );

			// Populate immediately in case the store already has data.
			rateMap  = buildRateMap();
			lastJson = JSON.stringify( rateMap );
		}

		// MutationObserver catches DOM re-renders (e.g. after address entry).
		var observer = new MutationObserver( function () {
			enrichAll( rateMap );
			syncSelectedBlocks();
		} );

		observer.observe( document.body, { childList: true, subtree: true } );

		// Radio-change events for instant selected-state feedback.
		document.addEventListener( 'change', function ( e ) {
			if (
				e.target &&
				e.target.type === 'radio' &&
				e.target.name &&
				e.target.name.indexOf( 'radio-control-wc-shipping-rates' ) === 0
			) {
				syncSelectedBlocks();
			}
		} );

		// Initial pass.
		enrichAll( rateMap );
		syncSelectedBlocks();
	}

	// Wait for the DOM to be ready; WC Blocks may render asynchronously
	// after DOMContentLoaded, but the subscribe/observer pattern handles that.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
