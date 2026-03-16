<?php
namespace BeTA\Bring\Admin;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\API\BookingService;
use BeTA\Bring\Woo\OrderData;
use WP_Error;

class OrderMetaBox {
	public static function register_meta_box(): void {
		// Register on both legacy (shop_order) and HPOS (woocommerce_page_wc-orders) screens.
		add_meta_box(
			'bbi_booking',
			__( 'Bring booking', 'bbi' ),
			[ __CLASS__, 'render' ],
			[ 'shop_order', 'woocommerce_page_wc-orders' ],
			'side',
			'default'
		);
	}

	public static function render( $post_or_order ): void {
		// HPOS passes a WC_Order object; legacy passes a WP_Post.
		if ( $post_or_order instanceof \WC_Order ) {
			$order    = $post_or_order;
			$order_id = $order->get_id();
		} else {
			$order_id = (int) $post_or_order->ID;
			$order    = wc_get_order( $order_id );
		}

		$settings    = new SettingsModel();
		$presets     = $settings->get_presets();
		$default_key = $settings->get_default_preset_key();

		$consignment = $order ? $order->get_meta( OrderData::META_CONSIGNMENT ) : '';
		$label       = $order ? $order->get_meta( OrderData::META_LABEL_URL )   : '';
		$tracking    = $order ? $order->get_meta( OrderData::META_TRACKING_URL ) : '';
		$booked_at   = $order ? $order->get_meta( OrderData::META_BOOKED_AT )   : '';

		$weight     = $order ? OrderData::get_order_weight( $order ) : 0;
		$to_postal  = $order ? esc_attr( $order->get_billing_postcode() ) : '';
		$to_country = $order ? esc_attr( $order->get_billing_country() ) : 'NO';

		wp_nonce_field( 'bbi_book_order', 'bbi_nonce' );

		echo '<p><label for="bbi_preset">' . esc_html__( 'Preset', 'bbi' ) . '</label></p>';
		echo '<select id="bbi_preset" name="preset"'
			. ' data-to-postal="' . $to_postal . '"'
			. ' data-to-country="' . $to_country . '">';

		foreach ( $presets as $key => $p ) {
			$sel              = selected( $default_key, $key, false );
			$requires_pickup  = ! empty( $p['requiresPickupPoint'] ) ? '1' : '0';
			printf(
				'<option value="%s" %s data-requires-pickup="%s">%s</option>',
				esc_attr( $key ),
				$sel,
				esc_attr( $requires_pickup ),
				esc_html( $p['label'] ?? $key )
			);
		}
		echo '</select>';

		// Pickup point selector – visible only for presets that require it.
		echo '<div id="bbi_pickup_wrap" style="display:none;">';
		echo '<p><label for="bbi_pickup_point">' . esc_html__( 'Pickup point', 'bbi' ) . '</label></p>';
		echo '<select id="bbi_pickup_point" name="pickup_point_id">'
			. '<option value="">' . esc_html__( 'Select pickup point', 'bbi' ) . '</option>'
			. '</select>';
		echo '</div>';

		echo '<p>' . sprintf(
			/* translators: %s: weight in kg */
			esc_html__( 'Weight (kg): %s', 'bbi' ),
			esc_html( (string) $weight )
		) . '</p>';

		echo '<p><button id="bbi_book_btn" class="button button-primary"'
			. ' data-order-id="' . esc_attr( (string) $order_id ) . '">'
			. esc_html__( 'Book shipment', 'bbi' )
			. '</button></p>';

		echo '<div id="bbi_status">';
		if ( $consignment ) {
			echo '<p>' . sprintf(
				/* translators: %s: consignment number */
				esc_html__( 'Consignment no: %s', 'bbi' ),
				esc_html( $consignment )
			) . '</p>';

			if ( $booked_at ) {
				echo '<p><small>' . esc_html(
					wp_date(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						strtotime( $booked_at )
					)
				) . '</small></p>';
			}

			if ( $label ) {
				printf(
					'<p><a class="button" href="%s" target="_blank">%s</a></p>',
					esc_url( $label ),
					esc_html__( 'Download label (PDF)', 'bbi' )
				);
			}

			if ( $tracking ) {
				printf(
					'<p><button class="button bbi-copy-tracking" data-url="%s">%s</button></p>',
					esc_attr( $tracking ),
					esc_html__( 'Copy tracking link', 'bbi' )
				);
			}
		}
		echo '</div>';
	}

	public static function ajax_book_order(): void {
		check_ajax_referer( 'bbi_book_order', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'bbi' ) ] );
		}

		$order_id        = isset( $_POST['order_id'] )        ? (int) $_POST['order_id']                                  : 0;
		$preset_key      = isset( $_POST['preset'] )          ? sanitize_text_field( wp_unslash( $_POST['preset'] ) )         : '';
		$weight          = isset( $_POST['weight'] )          ? (float) $_POST['weight']                                   : null;
		$pickup_point_id = isset( $_POST['pickup_point_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pickup_point_id'] ) ) : '';

		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid order', 'bbi' ) ] );
		}

		$settings = new SettingsModel();
		$preset   = $settings->get_preset( $preset_key );
		if ( ! $preset ) {
			wp_send_json_error( [ 'message' => __( 'Invalid preset', 'bbi' ) ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found', 'bbi' ) ] );
		}

		try {
			$params = [ 'weight' => $weight ];
			if ( $pickup_point_id ) {
				$params['pickup_point_id'] = $pickup_point_id;
			}

			$service = new BookingService( $settings );
			$result  = $service->book_order( $order, $preset, $params );

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

			// Add a visible note to the order timeline.
			$order->add_order_note(
				sprintf(
					/* translators: 1: consignment number, 2: service ID */
					__( 'Bring shipment booked. Consignment: %1$s, Service: %2$s', 'bbi' ),
					$arr['consignment_no'],
					$arr['service_id']
				)
			);

			$order->save();

			wp_send_json_success( [ 'message' => __( 'Booked successfully', 'bbi' ), 'data' => $arr ] );
		} catch ( WP_Error $e ) {
			wp_send_json_error( [ 'message' => $e->get_error_message() ] );
		}
	}
}
