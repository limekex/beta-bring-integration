<?php
namespace BeTA\Bring;

use BeTA\Bring\Admin\Settings;
use BeTA\Bring\Admin\OrderMetaBox;
use BeTA\Bring\Admin\Notices;
use BeTA\Bring\Woo\BulkBooking;
use BeTA\Bring\API\Routes;

class Plugin {
    private string $file;

    public function __construct( string $file ) {
        $this->file = $file;
    }

    public function init(): void {
        // Load logger early
        add_action( 'init', function () {
            \BeTA\Bring\Woo\Logger::init();
        } );

        // Admin assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Admin init
        add_action( 'admin_init', [ Settings::class, 'init' ] );

        // Meta box
        add_action( 'add_meta_boxes', [ OrderMetaBox::class, 'register_meta_box' ] );
        add_action( 'wp_ajax_bbi_book_order', [ OrderMetaBox::class, 'ajax_book_order' ] );

        // Bulk booking
        BulkBooking::init();

        // REST routes
        add_action( 'rest_api_init', [ Routes::class, 'register_routes' ] );

        // Notices helper
        Notices::init();
    }

    public function enqueue_admin_assets( string $hook ): void {
        // Only load on relevant admin pages
        $load = false;
        if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
            $post_type = get_post_type();
            if ( 'shop_order' === $post_type ) {
                $load = true;
            }
        }

        if ( 'woocommerce_page_wc-settings' === $hook ) {
            $load = true;
        }

        if ( ! $load ) {
            return;
        }

        wp_enqueue_style( 'bbi-admin', BBI_URL . 'assets/css/admin.css', [], BBI_VER );
        wp_enqueue_script( 'bbi-admin', BBI_URL . 'assets/js/admin.js', [ 'jquery' ], BBI_VER, true );

        wp_localize_script( 'bbi-admin', 'bbi_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bbi_book_order' ),
            'i18n'     => [
                'booking' => __( 'Booking...', 'bbi' ),
                'book'    => __( 'Book shipment', 'bbi' ),
                'download_label' => __( 'Download label (PDF)', 'bbi' ),
                'copy_tracking'  => __( 'Copy tracking link', 'bbi' ),
            ],
        ] );
    }
}
