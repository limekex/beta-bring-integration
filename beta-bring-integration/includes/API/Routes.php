<?php
namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\Woo\OrderData;

class Routes {
	public static function register_routes(): void {
		// Order label endpoint.
		register_rest_route( 'bbi/v1', '/order/(?P<id>\d+)/label', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'handle_get_label' ],
			'permission_callback' => [ __CLASS__, 'admin_permission' ],
		] );

		// Pickup points by country + postal code (admin — full payload).
		register_rest_route( 'bbi/v1', '/pickup-points/(?P<country>[A-Z]{2})/(?P<postalCode>[\w\s-]+)', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'handle_pickup_points' ],
			'permission_callback' => [ __CLASS__, 'admin_permission' ],
		] );

		// Pickup points for checkout (public — logged-in or guest).
		register_rest_route( 'bbi/v1', '/checkout/pickup-points/(?P<country>[A-Z]{2})/(?P<postalCode>[\w\s-]+)', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'handle_checkout_pickup_points' ],
			'permission_callback' => '__return_true',
		] );

		// Postal code lookup.
		register_rest_route( 'bbi/v1', '/postal-code/(?P<country>[A-Z]{2})/(?P<postalCode>[\w\s-]+)', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'handle_postal_code' ],
			'permission_callback' => [ __CLASS__, 'admin_permission' ],
		] );

		// Shipping guide: available products for given postal code pair.
		register_rest_route( 'bbi/v1', '/shipping-guide', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'handle_shipping_guide' ],
			'permission_callback' => [ __CLASS__, 'admin_permission' ],
		] );

		// Mybring customer / user settings.
		register_rest_route( 'bbi/v1', '/customer-settings', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'handle_customer_settings' ],
			'permission_callback' => [ __CLASS__, 'admin_permission' ],
		] );

		// Tracking — for logged-in customers viewing their own orders.
		register_rest_route( 'bbi/v1', '/tracking/(?P<order_id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'handle_tracking' ],
			'permission_callback' => [ __CLASS__, 'customer_order_permission' ],
		] );
	}

	public static function admin_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Check that the current user owns the order or is an admin.
	 */
	public static function customer_order_permission( \WP_REST_Request $request ): bool {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		$order_id = (int) $request['order_id'];
		if ( ! $order_id || ! is_user_logged_in() ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return (int) $order->get_customer_id() === get_current_user_id();
	}

	public static function handle_get_label( $request ) {
		$id = (int) $request['id'];
		if ( ! $id ) {
			return rest_ensure_response( [ 'error' => __( 'Invalid order id', 'bbi' ) ] );
		}

		$order = wc_get_order( $id );
		if ( ! $order ) {
			return rest_ensure_response( [ 'error' => __( 'Order not found', 'bbi' ) ] );
		}

		$label    = $order->get_meta( OrderData::META_LABEL_URL );
		$tracking = $order->get_meta( OrderData::META_TRACKING_URL );

		// Optional refresh param: verify label is still reachable.
		$refresh = $request->get_param( 'refresh' );
		if ( $refresh && $label ) {
			$client = new Client( new SettingsModel() );
			$resp   = $client->get( $label );
			if ( ! $resp['success'] ) {
				return rest_ensure_response( [ 'error' => __( 'Label not available', 'bbi' ) ] );
			}
		}

		return rest_ensure_response( [ 'label_url' => $label, 'tracking_url' => $tracking ] );
	}

	public static function handle_pickup_points( $request ) {
		$country     = strtoupper( sanitize_text_field( $request['country'] ) );
		$postal_code = sanitize_text_field( $request['postalCode'] );
		$max         = (int) ( $request->get_param( 'max' ) ?? 10 );

		$service = new PickupPointService( new SettingsModel() );
		$data    = $service->get_by_postal_code( $country, $postal_code, min( $max, 50 ) );

		return rest_ensure_response( $data );
	}

	public static function handle_postal_code( $request ) {
		$country     = strtoupper( sanitize_text_field( $request['country'] ) );
		$postal_code = sanitize_text_field( $request['postalCode'] );

		$service = new PostalCodeService( new SettingsModel() );
		$data    = $service->lookup( $country, $postal_code );

		return rest_ensure_response( $data );
	}

	public static function handle_shipping_guide( $request ) {
		$from_postal = sanitize_text_field( $request->get_param( 'fromPostalCode' ) ?? '' );
		$to_postal   = sanitize_text_field( $request->get_param( 'toPostalCode' ) ?? '' );

		if ( ! $from_postal || ! $to_postal ) {
			return new \WP_Error(
				'bbi_missing_params',
				__( 'fromPostalCode and toPostalCode are required', 'bbi' ),
				[ 'status' => 400 ]
			);
		}

		// Map REST param names to the keys expected by ShippingGuideService::get_products().
		$opts        = [];
		$param_map   = [
			'fromcountrycode' => 'fromcountry',
			'tocountrycode'   => 'tocountry',
			'weightInGrams'   => 'weight_grams',
			'language'        => 'language',
		];
		foreach ( $param_map as $param => $key ) {
			$val = $request->get_param( $param );
			if ( null !== $val ) {
				$opts[ $key ] = sanitize_text_field( (string) $val );
			}
		}

		$service = new ShippingGuideService( new SettingsModel() );
		$data    = $service->get_products( $from_postal, $to_postal, [], $opts );

		return rest_ensure_response( $data );
	}

	public static function handle_customer_settings( $request ) {
		$service = new CustomerService( new SettingsModel() );
		$data    = $service->get_user_settings();

		return rest_ensure_response( $data );
	}

	/**
	 * Public checkout endpoint: returns a simplified list of pickup points.
	 *
	 * Only exposes id, name, address, and visiting address — no internal
	 * Bring data like opening hours or location types is leaked.
	 */
	public static function handle_checkout_pickup_points( $request ) {
		$country     = strtoupper( sanitize_text_field( $request['country'] ) );
		$postal_code = sanitize_text_field( $request['postalCode'] );

		// Rate-limit by transient to prevent abuse on the public endpoint.
		$throttle_key = 'bbi_pp_' . md5( $country . $postal_code . ( wp_get_session_token() ?: '' ) );
		if ( get_transient( $throttle_key ) ) {
			// Return cached result.
			$cached = get_transient( $throttle_key . '_data' );
			if ( is_array( $cached ) ) {
				return rest_ensure_response( $cached );
			}
		}

		$service = new PickupPointService( new SettingsModel() );
		$data    = $service->get_by_postal_code( $country, $postal_code, 10 );

		// Simplify the response for the public endpoint.
		$points = [];
		$raw    = $data['pickupPoint'] ?? [];
		if ( is_array( $raw ) ) {
			foreach ( $raw as $pp ) {
				$points[] = [
					'id'      => $pp['id'] ?? '',
					'name'    => $pp['name'] ?? '',
					'address' => trim( ( $pp['visitingAddress'] ?? $pp['address'] ?? '' ) . ', ' . ( $pp['postalCode'] ?? '' ) . ' ' . ( $pp['city'] ?? '' ), ', ' ),
				];
			}
		}

		$result = [ 'pickupPoints' => $points ];

		// Cache for 5 minutes.
		set_transient( $throttle_key, true, 300 );
		set_transient( $throttle_key . '_data', $result, 300 );

		return rest_ensure_response( $result );
	}

	/**
	 * Tracking endpoint: returns tracking events for the customer's order.
	 */
	public static function handle_tracking( $request ) {
		$order_id = (int) $request['order_id'];
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'bbi_order_not_found', __( 'Order not found', 'bbi' ), [ 'status' => 404 ] );
		}

		$consignment_no = $order->get_meta( OrderData::META_CONSIGNMENT );
		if ( ! $consignment_no ) {
			return rest_ensure_response( [ 'status' => 'not_booked', 'events' => [] ] );
		}

		$lang    = sanitize_text_field( $request->get_param( 'lang' ) ?? 'no' );
		$service = new TrackingService( new SettingsModel() );
		$data    = $service->track( $consignment_no, $lang );

		if ( isset( $data['error'] ) ) {
			return rest_ensure_response( [ 'status' => 'error', 'message' => $data['error'], 'events' => [] ] );
		}

		// Parse the Bring response into a simplified event list.
		$events      = [];
		$last_status = '';
		$consignment = $data['consignmentSet'][0] ?? [];
		$packages    = $consignment['packageSet'] ?? [];

		foreach ( $packages as $pkg ) {
			foreach ( $pkg['eventSet'] ?? [] as $evt ) {
				$status       = $evt['status'] ?? '';
				$last_status  = $last_status ?: $status;
				$events[]     = [
					'status'      => $status,
					'description' => $evt['description'] ?? '',
					'city'        => $evt['city'] ?? '',
					'country'     => $evt['country'] ?? '',
					'timestamp'   => $evt['dateIso'] ?? $evt['displayDate'] ?? '',
					'displayDate' => $evt['displayDate'] ?? '',
					'displayTime' => $evt['displayTime'] ?? '',
				];
			}
		}

		$tracking_url = $order->get_meta( OrderData::META_TRACKING_URL );

		return rest_ensure_response( [
			'status'        => $last_status ?: 'unknown',
			'consignment'   => $consignment_no,
			'tracking_url'  => $tracking_url,
			'events'        => $events,
		] );
	}
}
