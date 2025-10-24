<?php
namespace BeTA\Bring;

class Autoloader {
    /**
     * Register a basic PSR-4 autoloader mapping a single namespace prefix to directory.
     *
     * @param string $base_dir Absolute path to base directory that contains the namespace root.
     * @param string $prefix   Namespace prefix (e.g. "BeTA\\Bring\\").
     */
    public static function register( string $base_dir, string $prefix = 'BeTA\\Bring\\' ): void {
        spl_autoload_register( function ( $class ) use ( $base_dir, $prefix ) {
            // Only handle classes in our namespace
            if ( 0 !== strpos( $class, $prefix ) ) {
                return;
            }

            $relative_class = substr( $class, strlen( $prefix ) );
            $file = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        } );
    }
}
