<?php
namespace BeTA\Bring\Admin;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\API\BookingService;
use BeTA\Bring\Woo\OrderData;
use BeTA\Bring\Util\Arr;
use WP_Error;

class OrderMetaBox {
    public static function register_meta_box(): void {
        add_meta_box( 'bbi_booking', __( 'Bring booking', 'bbi' ), [ __CLASS__, 'render' ], 'shop_order', 'side', 'default' );
    }

    public static function render( $post ): void {
        $order_id = (int) $post->ID;
        $settings = new SettingsModel();
        $presets = $settings->get_presets();
        $default_key = $settings->get_default_preset_key();

        $current = OrderData::get_meta( $order_id, OrderData::META_BOOKING );
        $label = OrderData::get_meta( $order_id, OrderData::META_LABEL_URL );
        $tracking = OrderData::get_meta( $order_id, OrderData::META_TRACKING_URL );

        wp_nonce_field( 'bbi_book_order', 'bbi_nonce' );

        echo '<p><label for="bbi_preset">' . esc_html__( 'Preset', 'bbi' ) . '</label></p>';
        echo '<select id="bbi_preset" name="preset">';
        foreach ( $presets as $key => $p ) {
            $sel = selected( $default_key, $key, false );
            printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), $sel, esc_html( $p['label'] ?? $key ) );
        }
        echo '</select>';

        $order = wc_get_order( $order_id );
        $weight = OrderData::get_order_weight( $order );

        echo '<p>' . sprintf( esc_html__( 'Weight (kg): %s', 'bbi' ), esc_html( (string) $weight ) ) . '</p>';

        echo '<p><button id="bbi_book_btn" class="button button-primary">' . esc_html__( 'Book shipment', 'bbi' ) . '</button></p>';

        echo '<div id="bbi_status">';
        if ( $current ) {
            $meta = is_string( $current ) ? json_decode( $current, true ) : $current;
            if ( $meta ) {
                echo '<p>' . sprintf( esc_html__( 'Consignment no: %s', 'bbi' ), esc_html( $meta['consignment_no'] ?? '' ) ) . '</p>';
                if ( ! empty( $meta['label_url'] ) ) {
                    printf( '<p><a class="button" href="%s" target="_blank">%s</a></p>', esc_url( $meta['label_url'] ), esc_html__( 'Download label (PDF)', 'bbi' ) );
                }
                if ( ! empty( $meta['tracking_url'] ) ) {
                    printf( '<p><button class="button bbi-copy-tracking" data-url="%s">%s</button></p>', esc_attr( $meta['tracking_url'] ), esc_html__( 'Copy tracking link', 'bbi' ) );
                }
            }
        }
        echo '</div>';
    }

    public static function ajax_book_order(): void {
        check_ajax_referer( 'bbi_book_order', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'bbi' ) ] );
        }

        $order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
        $preset_key = sanitize_text_field( wp_unslash( $_POST['preset'] ?? '' ) );
        $weight = isset( $_POST['weight'] ) ? (float) $_POST['weight'] : null;

        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid order', 'bbi' ) ] );
        }

        $settings = new SettingsModel();
        $preset = $settings->get_preset( $preset_key );
        if ( ! $preset ) {
            wp_send_json_error( [ 'message' => __( 'Invalid preset', 'bbi' ) ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( [ 'message' => __( 'Order not found', 'bbi' ) ] );
        }

        try {
            $service = new BookingService( $settings );
            $result = $service->book_order( $order, $preset, [ 'weight' => $weight ] );

            // Save meta
            $arr = $result->to_array();
            update_post_meta( $order_id, OrderData::META_BOOKING, wp_json_encode( $arr ) );
            if ( $arr['label_url'] ) {
                update_post_meta( $order_id, OrderData::META_LABEL_URL, $arr['label_url'] );
            }
            if ( $arr['tracking_url'] ) {
                update_post_meta( $order_id, OrderData::META_TRACKING_URL, $arr['tracking_url'] );
            }
            update_post_meta( $order_id, OrderData::META_CONSIGNMENT, $arr['consignment_no'] );
            update_post_meta( $order_id, OrderData::META_SERVICE_ID, $arr['service_id'] );
            update_post_meta( $order_id, OrderData::META_BOOKED_AT, $arr['booked_at'] );

            wp_send_json_success( [ 'message' => __( 'Booked successfully', 'bbi' ), 'data' => $arr ] );
        } catch ( WP_Error $e ) {
            wp_send_json_error( [ 'message' => $e->get_error_message() ] );
        }
    }
}
