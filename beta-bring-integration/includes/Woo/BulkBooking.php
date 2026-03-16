<?php
namespace BeTA\Bring\Woo;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\API\BookingService;

class BulkBooking {
	public static function init(): void {
		// HPOS orders list (woocommerce_page_wc-orders).
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ __CLASS__, 'register_bulk_action' ] );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ __CLASS__, 'handle_bulk_action' ], 10, 3 );

		// Legacy post-based orders list (edit-shop_order).
		add_filter( 'bulk_actions-edit-shop_order', [ __CLASS__, 'register_bulk_action' ] );
		add_filter( 'handle_bulk_actions-edit-shop_order', [ __CLASS__, 'handle_bulk_action' ], 10, 3 );
	}

	public static function register_bulk_action( array $actions ): array {
		$actions['bbi_bulk_book'] = __( 'Book Bring label (default preset)', 'bbi' );
		return $actions;
	}

	public static function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'bbi_bulk_book' !== $action ) {
			return $redirect_to;
		}

		$settings   = new SettingsModel();
		$preset_key = $settings->get_default_preset_key();
		$preset     = $settings->get_preset( $preset_key );

		if ( ! $preset ) {
			return add_query_arg( [ 'bbi_bulk_error' => 'no_preset' ], $redirect_to );
		}

		$success = 0;
		$fail    = 0;

		foreach ( $post_ids as $id ) {
			$order = wc_get_order( (int) $id );
			if ( ! $order ) {
				$fail++;
				continue;
			}

			try {
				$service = new BookingService( $settings );
				$result  = $service->book_order( $order, $preset );

				$arr = $result->to_array();

				// Save meta via WC_Order for HPOS compatibility.
				$order->update_meta_data( OrderData::META_BOOKING,     wp_json_encode( $arr ) );
				$order->update_meta_data( OrderData::META_CONSIGNMENT, $arr['consignment_no'] );
				$order->update_meta_data( OrderData::META_SERVICE_ID,  $arr['service_id'] );
				$order->update_meta_data( OrderData::META_BOOKED_AT,   $arr['booked_at'] );
				if ( $arr['label_url'] ) {
					$order->update_meta_data( OrderData::META_LABEL_URL, $arr['label_url'] );
				}
				if ( $arr['tracking_url'] ) {
					$order->update_meta_data( OrderData::META_TRACKING_URL, $arr['tracking_url'] );
				}

				// Add order note so the action appears in the order timeline.
				$order->add_order_note(
					sprintf(
						/* translators: 1: consignment number, 2: service ID */
						__( 'Bring shipment booked. Consignment: %1$s, Service: %2$s', 'bbi' ),
						$arr['consignment_no'],
						$arr['service_id']
					)
				);

				$order->save();

				$success++;
			} catch ( \Exception $e ) {
				$fail++;
			}

			usleep( 300000 ); // small delay to avoid rate-limiting
		}

		$redirect_to = add_query_arg( [ 'bbi_bulk_success' => $success, 'bbi_bulk_fail' => $fail ], $redirect_to );
		return $redirect_to;
	}
}
