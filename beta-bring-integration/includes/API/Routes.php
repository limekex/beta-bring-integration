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

		// Pickup points by country + postal code.
		register_rest_route( 'bbi/v1', '/pickup-points/(?P<country>[A-Z]{2})/(?P<postalCode>[\w\s-]+)', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'handle_pickup_points' ],
			'permission_callback' => [ __CLASS__, 'admin_permission' ],
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
	}

	public static function admin_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
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

		$opts = [];
		foreach ( [ 'fromcountrycode', 'tocountrycode', 'weightInGrams', 'volumeInDm3', 'language' ] as $key ) {
			$val = $request->get_param( $key );
			if ( null !== $val ) {
				$opts[ $key ] = sanitize_text_field( (string) $val );
			}
		}

		$service = new ShippingGuideService( new SettingsModel() );
		$data    = $service->get_products( $from_postal, $to_postal, $opts );

		return rest_ensure_response( $data );
	}

	public static function handle_customer_settings( $request ) {
		$service = new CustomerService( new SettingsModel() );
		$data    = $service->get_user_settings();

		return rest_ensure_response( $data );
	}
}
