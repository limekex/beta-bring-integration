<?php
namespace BeTA\Bring\Woo;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\API\BookingService;

class BulkBooking {
    public static function init(): void {
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

        $settings = new SettingsModel();
        $preset_key = $settings->get_default_preset_key();
        $preset = $settings->get_preset( $preset_key );

        $success = 0;
        $fail = 0;

        foreach ( $post_ids as $id ) {
            $order = wc_get_order( $id );
            if ( ! $order ) {
                $fail++;
                continue;
            }

            try {
                $service = new BookingService( $settings );
                $result = $service->book_order( $order, $preset );

                $arr = $result->to_array();
                update_post_meta( $id, OrderData::META_BOOKING, wp_json_encode( $arr ) );
                if ( $arr['label_url'] ) {
                    update_post_meta( $id, OrderData::META_LABEL_URL, $arr['label_url'] );
                }
                if ( $arr['tracking_url'] ) {
                    update_post_meta( $id, OrderData::META_TRACKING_URL, $arr['tracking_url'] );
                }

                $success++;
            } catch ( \Exception $e ) {
                $fail++;
            }

            usleep( 300000 ); // small delay
        }

        $redirect_to = add_query_arg( [ 'bbi_bulk_success' => $success, 'bbi_bulk_fail' => $fail ], $redirect_to );
        return $redirect_to;
    }
}
