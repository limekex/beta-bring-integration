<?php
namespace BeTA\Bring\Util;

class Arr {
    public static function get( array $arr, $key, $default = null ) {
        if ( is_array( $key ) ) {
            foreach ( $key as $k ) {
                if ( isset( $arr[ $k ] ) ) {
                    return $arr[ $k ];
                }
            }
            return $default;
        }

        return $arr[ $key ] ?? $default;
    }
}
