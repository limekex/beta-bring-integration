<?php
namespace BeTA\Bring;

use BeTA\Bring\Admin\Settings;
use BeTA\Bring\Admin\OrderMetaBox;
use BeTA\Bring\Admin\OrderListColumns;
use BeTA\Bring\Admin\Notices;
use BeTA\Bring\Woo\BulkBooking;
use BeTA\Bring\Woo\ShippingMethod;
use BeTA\Bring\API\Routes;

class Plugin {
	private string $file;

	public function __construct( string $file ) {
		$this->file = $file;
	}

	public function init(): void {
		// Declare HPOS (High-Performance Order Storage) compatibility.
		add_action( 'before_woocommerce_init', function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->file, true );
			}
		} );

		// Load logger early.
		add_action( 'init', function () {
			\BeTA\Bring\Woo\Logger::init();
		} );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Settings filters must be registered before WooCommerce processes
		// settings saves (which happens on admin_init). Using the earlier
		// 'init' hook guarantees the filters are in place in time.
		add_action( 'init', [ Settings::class, 'init' ] );

		// Meta box (single order detail page).
		add_action( 'add_meta_boxes', [ OrderMetaBox::class, 'register_meta_box' ] );
		add_action( 'wp_ajax_bbi_book_order', [ OrderMetaBox::class, 'ajax_book_order' ] );

		// Order list columns (overview page).
		OrderListColumns::init();

		// Bulk booking.
		BulkBooking::init();

		// Register Bring as a WooCommerce shipping method (for checkout rate display).
		add_filter( 'woocommerce_shipping_methods', static function ( array $methods ): array {
			$methods['bbi_bring'] = ShippingMethod::class;
			return $methods;
		} );

		// Enrich the shipping rate label with logo, delivery estimate, description
		// and pickup point info.  Registered here — not inside ShippingMethod::init()
		// — so it fires even when WooCommerce serves cached rates from the session
		// (in which case ShippingMethod is never instantiated on the current request).
		add_filter( 'woocommerce_cart_shipping_method_full_label', [ ShippingMethod::class, 'filter_rate_label' ], 10, 2 );

		// REST routes.
		add_action( 'rest_api_init', [ Routes::class, 'register_routes' ] );

		// Frontend CSS for enriched shipping rate labels in cart/checkout.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// Notices helper.
		Notices::init();
	}

	public function enqueue_frontend_assets(): void {
		// Only load on cart and checkout pages.
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			wp_enqueue_style( 'bbi-checkout', BBI_URL . 'assets/css/checkout.css', [], BBI_VER );
			wp_enqueue_script( 'bbi-checkout', BBI_URL . 'assets/js/checkout.js', [ 'jquery' ], BBI_VER, true );
		}
	}

	public function enqueue_admin_assets( string $hook ): void {
		$load_css = false;
		$load_js  = false;

		// Single order detail page – legacy post editor.
		if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && 'shop_order' === get_post_type() ) {
			$load_css = true;
			$load_js  = true;
		}

		// HPOS order detail page (woocommerce_page_wc-orders?action=edit&id=…).
		if ( 'woocommerce_page_wc-orders' === $hook ) {
			$load_css = true;
			// Only load JS when editing a specific order, not on the list view.
			$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
			if ( 'edit' === $action || isset( $_GET['id'] ) ) {
				$load_js = true;
			}
		}

		// Legacy orders list (edit.php?post_type=shop_order) – only CSS for the column.
		if ( 'edit.php' === $hook && isset( $_GET['post_type'] ) && 'shop_order' === sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) ) {
			$load_css = true;
		}

		// Settings page.
		if ( 'woocommerce_page_wc-settings' === $hook ) {
			$load_css = true;
			$load_js  = true;
		}

		if ( ! $load_css ) {
			return;
		}

		wp_enqueue_style( 'bbi-admin', BBI_URL . 'assets/css/admin.css', [], BBI_VER );

		if ( $load_js ) {
			wp_enqueue_script( 'bbi-admin', BBI_URL . 'assets/js/admin.js', [ 'jquery' ], BBI_VER, true );

			wp_localize_script( 'bbi-admin', 'bbi_ajax', [
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'bbi_book_order' ),
				'rest_url'   => rest_url( 'bbi/v1' ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'       => [
					'booking'        => __( 'Booking...', 'bbi' ),
					'book'           => __( 'Book shipment', 'bbi' ),
					'download_label' => __( 'Download label (PDF)', 'bbi' ),
					'copy_tracking'  => __( 'Copy tracking link', 'bbi' ),
					'copied'         => __( 'Copied!', 'bbi' ),
					'loading'        => __( 'Loading...', 'bbi' ),
					'select_pickup'  => __( 'Select pickup point', 'bbi' ),
					'no_pickup'      => __( 'No pickup points found', 'bbi' ),
				],
			] );
		}
	}
}
