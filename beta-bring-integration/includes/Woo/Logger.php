<?php
namespace BeTA\Bring\Woo;

class Logger {
    private static $logger = null;
    private const SOURCE = 'bbi';

    public static function init(): void {
        if ( null === self::$logger ) {
            self::$logger = wc_get_logger();
        }
    }

    /**
     * @return \WC_Logger
     */
    public static function get() {
        if ( null === self::$logger ) {
            self::init();
        }

        return self::$logger;
    }

    public static function info( string $message, array $context = [] ): void {
        self::get()->info( $message, array_merge( [ 'source' => self::SOURCE ], $context ) );
    }

    public static function error( string $message, array $context = [] ): void {
        self::get()->error( $message, array_merge( [ 'source' => self::SOURCE ], $context ) );
    }

    /**
     * Write a debug-level log entry.
     *
     * Only writes when the "Debug mode" setting is enabled under
     * WooCommerce → Settings → Shipping → BeTA Bring.
     * Logs are visible in WooCommerce → Status → Logs (source: bbi).
     */
    public static function debug( string $message, array $context = [] ): void {
        if ( 'yes' !== get_option( 'bbi_debug_mode', 'no' ) ) {
            return;
        }

        self::get()->debug( $message, array_merge( [ 'source' => self::SOURCE ], $context ) );
    }
}
