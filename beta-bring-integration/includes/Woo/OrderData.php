<?php
namespace BeTA\Bring\Woo;

class OrderData {
    public const META_BOOKING      = '_bbi_booking';
    public const META_LABEL_URL    = '_bbi_label_url';
    public const META_TRACKING_URL = '_bbi_tracking_url';
    public const META_CONSIGNMENT  = '_bbi_consignment_no';
    public const META_SERVICE_ID   = '_bbi_service_id';
    public const META_BOOKED_AT    = '_bbi_booked_at';

    /**
     * Calculate total weight for order-like object.
     * Accepts a WC_Order or any object with get_items() returning item objects
     * with get_product() and get_quantity() methods.
     *
     * @param object $order
     * @return float
     */
    public static function get_order_weight( $order ): float {
        $weight = 0.0;
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $qty = $item->get_quantity() ?: 1;
                $w   = (float) $product->get_weight();
                $weight += $w * $qty;
            }
        }

        // Woo stores weight in units, assume kg if using kg store settings. Convert string to float.
        return round( (float) $weight, 3 );
    }

    /**
     * Extract recipient address from order-like object.
     *
     * @param object $order
     * @return array
     */
    public static function get_recipient_from_order( $order ): array {
        return [
            'name'      => $order->get_formatted_billing_full_name() ?: trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
            'phone'     => $order->get_billing_phone(),
            'email'     => $order->get_billing_email(),
            'address1'  => $order->get_billing_address_1(),
            'address2'  => $order->get_billing_address_2(),
            'postcode'  => $order->get_billing_postcode(),
            'city'      => $order->get_billing_city(),
            'country'   => $order->get_billing_country(),
        ];
    }

    public static function update_meta( int $order_id, string $key, $value ): void {
        update_post_meta( $order_id, $key, $value );
    }

    public static function get_meta( int $order_id, string $key ) {
        return get_post_meta( $order_id, $key, true );
    }
}
