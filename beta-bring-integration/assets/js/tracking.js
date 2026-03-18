/**
 * Bring Shipment Tracking – My Account order detail page.
 *
 * Fetches tracking events from the bbi/v1/tracking/{order_id} REST endpoint
 * and renders a timeline in the #bbi-tracking-events container.
 */
( function () {
	'use strict';

	var container = document.getElementById( 'bbi-tracking-events' );
	if ( ! container ) {
		return;
	}

	var orderId = container.dataset.orderId;
	var restUrl = container.dataset.restUrl;
	var nonce   = container.dataset.nonce;

	if ( ! orderId || ! restUrl ) {
		return;
	}

	var lang = document.documentElement.lang || 'no';
	// Normalise e.g. "nb-NO" → "no"
	if ( lang.indexOf( 'nb' ) === 0 || lang.indexOf( 'nn' ) === 0 ) {
		lang = 'no';
	} else {
		lang = lang.substring( 0, 2 );
	}

	var url = restUrl.replace( /\/$/, '' ) + '/tracking/' + orderId + '?lang=' + encodeURIComponent( lang );

	fetch( url, {
		credentials: 'same-origin',
		headers: { 'X-WP-Nonce': nonce }
	} )
	.then( function ( res ) { return res.json(); } )
	.then( function ( data ) {
		renderTracking( data );
	} )
	.catch( function () {
		container.innerHTML = '<p>' + escHtml( bbi_tracking_i18n.error ) + '</p>';
	} );

	/**
	 * Status → human-readable CSS class and colour.
	 */
	var STATUS_MAP = {
		'DELIVERED':             'delivered',
		'IN_TRANSIT':            'in-transit',
		'TRANSPORT_TO_RECIPIENT':'in-transit',
		'READY_FOR_PICKUP':      'ready',
		'NOTIFICATION_SENT':     'info',
		'PRE_NOTIFIED':          'info',
		'HANDED_IN':             'info',
		'COLLECTED':             'info',
		'TERMINAL':              'in-transit',
		'ATTEMPTED_DELIVERY':    'warning',
		'DEVIATION':             'warning',
		'CUSTOMS':               'info',
		'RETURN':                'warning',
		'DELIVERY_CANCELLED':    'warning',
		'DELIVERED_SENDER':      'warning',
		'UNKNOWN':               'info',
	};

	function statusClass( status ) {
		return STATUS_MAP[ status ] || 'info';
	}

	function escHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str || '' ) );
		return div.innerHTML;
	}

	function renderTracking( data ) {
		if ( ! data || data.status === 'not_booked' ) {
			container.innerHTML = '<p>' + escHtml( bbi_tracking_i18n.not_booked ) + '</p>';
			return;
		}

		if ( data.status === 'error' ) {
			container.innerHTML = '<p>' + escHtml( data.message || bbi_tracking_i18n.error ) + '</p>';
			return;
		}

		var events = data.events || [];
		if ( ! events.length ) {
			container.innerHTML = '<p>' + escHtml( bbi_tracking_i18n.no_events ) + '</p>';
			return;
		}

		// Build status badge.
		var latestStatus = data.status || 'UNKNOWN';
		var badgeClass   = 'bbi-tracking-badge bbi-tracking-badge--' + statusClass( latestStatus );
		var statusLabel  = bbi_tracking_i18n[ 'status_' + latestStatus ] || latestStatus.replace( /_/g, ' ' );

		var html = '<div class="bbi-tracking-status">';
		html += '<span class="' + badgeClass + '">' + escHtml( statusLabel ) + '</span>';
		html += '</div>';

		// Build timeline.
		html += '<ol class="bbi-tracking-timeline">';
		for ( var i = 0; i < events.length; i++ ) {
			var evt = events[ i ];
			var cls = statusClass( evt.status );
			var when = '';
			if ( evt.displayDate ) {
				when = evt.displayDate;
				if ( evt.displayTime ) {
					when += ' ' + evt.displayTime;
				}
			}

			html += '<li class="bbi-tracking-event bbi-tracking-event--' + cls + '">';
			html += '<div class="bbi-tracking-event-dot"></div>';
			html += '<div class="bbi-tracking-event-content">';
			html += '<span class="bbi-tracking-event-desc">' + escHtml( evt.description ) + '</span>';
			if ( when || evt.city ) {
				html += '<span class="bbi-tracking-event-meta">';
				if ( when ) {
					html += escHtml( when );
				}
				if ( evt.city ) {
					html += ( when ? ' — ' : '' ) + escHtml( evt.city );
				}
				html += '</span>';
			}
			html += '</div>';
			html += '</li>';
		}
		html += '</ol>';

		container.innerHTML = html;
	}
} )();
