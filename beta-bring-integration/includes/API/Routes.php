<?php
namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\Woo\OrderData;

class Routes {
    public static function register_routes(): void {
        register_rest_route( 'bbi/v1', '/order/(?P<id>\d+)/label', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'handle_get_label' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_woocommerce' );
            },
        ] );
    }

    public static function handle_get_label( $request ) {
        $id = (int) $request['id'];
        if ( ! $id ) {
            return rest_ensure_response( [ 'error' => __( 'Invalid order id', 'bbi' ) ] );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return rest_ensure_response( [ 'error' => __( 'Forbidden', 'bbi' ) ] );
        }

        $label = OrderData::get_meta( $id, OrderData::META_LABEL_URL );
        $tracking = OrderData::get_meta( $id, OrderData::META_TRACKING_URL );

        // Optional refresh param: check availability by doing a HEAD/GET of the label URL.
        $refresh = $request->get_param( 'refresh' );
        if ( $refresh && $label ) {
            $client = new Client( new SettingsModel() );
            $resp = $client->get( $label );
            if ( ! $resp['success'] ) {
                return rest_ensure_response( [ 'error' => __( 'Label not available', 'bbi' ) ] );
            }
        }

        return rest_ensure_response( [ 'label_url' => $label, 'tracking_url' => $tracking ] );
    }
}
