<?php
/**
 * Plugin Name: BeTA Bring Integration
 * Plugin URI:  https://example.com/beta-bring-integration
 * Description: Bring booking integration for WooCommerce — book shipments and fetch labels from the order screen.
 * Version:     0.1.0
 * Author:      BeTA iT
 * Requires at least: 6.3
 * Requires PHP: 8.1
 * WC tested up to: 8.9
 * Text Domain: bbi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'BBI_VER', '0.1.0' );
define( 'BBI_FILE', __FILE__ );
define( 'BBI_DIR', dirname( __FILE__ ) );
define( 'BBI_URL', plugin_dir_url( __FILE__ ) );

require_once BBI_DIR . '/includes/Autoloader.php';
\BeTA\Bring\Autoloader::register( BBI_DIR . '/includes', 'BeTA\\Bring\\' );

add_action( 'plugins_loaded', function () {
    // Load translations
    load_plugin_textdomain( 'bbi', false, dirname( plugin_basename( BBI_FILE ) ) . '/languages' );

    // Minimal WooCommerce check
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'BeTA Bring Integration requires WooCommerce to be active.', 'bbi' ) . '</p></div>';
        } );
        return;
    }

    $plugin = new \BeTA\Bring\Plugin( BBI_FILE );
    $plugin->init();
} );
