<?php
namespace BeTA\Bring;

use BeTA\Bring\Admin\Settings;
use BeTA\Bring\Admin\OrderMetaBox;
use BeTA\Bring\Admin\OrderListColumns;
use BeTA\Bring\Admin\Notices;
use BeTA\Bring\Admin\Email\BookingEmail;
use BeTA\Bring\Woo\BulkBooking;
use BeTA\Bring\Woo\BlocksIntegration;
use BeTA\Bring\Woo\ShippingMethod;
use BeTA\Bring\Woo\TrackingPage;
use BeTA\Bring\Woo\OrderData;
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

		// WooCommerce Blocks / Store API integration (shipping rate extension data).
		BlocksIntegration::init();

		// Register Bring as a WooCommerce shipping method (for checkout rate display).
		add_filter( 'woocommerce_shipping_methods', static function ( array $methods ): array {
			$methods['bbi_bring'] = ShippingMethod::class;
			return $methods;
		} );

		// Include the plugin version in the WooCommerce shipping session transient key.
		// WooCommerce caches computed shipping rates per-package in the session; without
		// this, a plugin update does not bust the old session cache, so rates calculated
		// before the fix (e.g. fallback prices) continue to be served until the customer
		// changes their cart or destination.
		add_filter( 'woocommerce_shipping_package_transient_key', static function ( string $key ): string {
			return $key . '_bbi' . sanitize_key( BBI_VER );
		} );

		// Enrich the shipping rate label with logo, delivery estimate, description
		// and pickup point info.  Registered here — not inside ShippingMethod::init()
		// — so it fires even when WooCommerce serves cached rates from the session
		// (in which case ShippingMethod is never instantiated on the current request).
		add_filter( 'woocommerce_cart_shipping_method_full_label', [ ShippingMethod::class, 'filter_rate_label' ], 10, 2 );

		// REST routes.
		add_action( 'rest_api_init', [ Routes::class, 'register_routes' ] );

		// WC AJAX handler for saving pickup point to session.
		// Using wc_ajax_ (not REST) because WC session is only available in the frontend AJAX pipeline.
		add_action( 'wc_ajax_bbi_save_pickup', [ $this, 'ajax_save_pickup_to_session' ] );
		add_action( 'wc_ajax_nopriv_bbi_save_pickup', [ $this, 'ajax_save_pickup_to_session' ] );

		// Frontend CSS for enriched shipping rate labels in cart/checkout.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// Notices helper.
		Notices::init();

		// Register custom WooCommerce email for Bring shipment booking notifications.
		add_filter( 'woocommerce_email_classes', static function ( array $emails ): array {
			$emails['BBI_Booking_Email'] = new BookingEmail();
			return $emails;
		} );

		// Tracking section on My Account → View Order pages.
		TrackingPage::init();

		// Save the customer's pickup point choice from checkout to order meta.
		add_action( 'woocommerce_checkout_create_order', [ $this, 'save_pickup_point_meta' ], 10, 2 );
	}

	/**
	 * Save the customer's pickup point choice to order meta during checkout.
	 *
	 * @param \WC_Order $order The order being created.
	 * @param array     $data  Posted checkout data.
	 */
	public function save_pickup_point_meta( \WC_Order $order, array $data ): void {
		$pickup_id = isset( $_POST['bbi_pickup_point_id'] )
			? sanitize_text_field( wp_unslash( $_POST['bbi_pickup_point_id'] ) )
			: '';

		if ( $pickup_id ) {
			$order->update_meta_data( OrderData::META_PICKUP_POINT, $pickup_id );

			$pickup_name = isset( $_POST['bbi_pickup_point_name'] )
				? sanitize_text_field( wp_unslash( $_POST['bbi_pickup_point_name'] ) )
				: '';
			if ( $pickup_name ) {
				$order->update_meta_data( OrderData::META_PICKUP_NAME, $pickup_name );
			}
		}

		// Clear the session data so it doesn't carry over to the next order.
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'bbi_pickup_point_id', '' );
			WC()->session->set( 'bbi_pickup_point_name', '' );
		}
	}

	/**
	 * WC AJAX handler: save the customer's pickup point choice to the WC session.
	 */
	public function ajax_save_pickup_to_session(): void {
		check_ajax_referer( 'bbi-save-pickup', 'security' );

		$pickup_id   = isset( $_POST['pickup_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pickup_id'] ) ) : '';
		$pickup_name = isset( $_POST['pickup_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pickup_name'] ) ) : '';

		\BeTA\Bring\Woo\Logger::info( 'wc_ajax bbi_save_pickup', [ 'pickup_id' => $pickup_id, 'pickup_name' => $pickup_name ] );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'bbi_pickup_point_id', $pickup_id );
			WC()->session->set( 'bbi_pickup_point_name', $pickup_name );
		}

		wp_send_json_success();
	}

	public function enqueue_frontend_assets(): void {
		// My Account → View Order: tracking assets.
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_enqueue_style( 'bbi-tracking', BBI_URL . 'assets/css/tracking.css', [], BBI_VER );
			wp_enqueue_script( 'bbi-tracking', BBI_URL . 'assets/js/tracking.js', [], BBI_VER, true );
			wp_localize_script( 'bbi-tracking', 'bbi_tracking_i18n', [
				'loading'     => __( 'Loading tracking information…', 'bbi' ),
				'error'       => __( 'Could not load tracking information.', 'bbi' ),
				'not_booked'  => __( 'Shipment not yet booked.', 'bbi' ),
				'delivered'   => __( 'Delivered', 'bbi' ),
				'in_transit'  => __( 'In transit', 'bbi' ),
				'ready'       => __( 'Ready for pickup', 'bbi' ),
				'unknown'     => __( 'Unknown status', 'bbi' ),
			] );
		}

		// Only load checkout assets on cart and checkout pages.
		if ( ! function_exists( 'is_cart' ) || ( ! is_cart() && ! is_checkout() ) ) {
			return;
		}

		// Classic checkout: enriched labels via the woocommerce_cart_shipping_method_full_label filter.
		wp_enqueue_style( 'bbi-checkout', BBI_URL . 'assets/css/checkout.css', [], BBI_VER );
		wp_enqueue_script( 'bbi-checkout', BBI_URL . 'assets/js/checkout.js', [ 'jquery' ], BBI_VER, true );
		// Pass the customer's session postcode so the cart page (which has no address fields) can still fetch pickup points.
		$customer_postcode = '';
		$customer_country  = 'NO';
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$customer_postcode = WC()->customer->get_shipping_postcode() ?: WC()->customer->get_billing_postcode();
			$customer_country  = WC()->customer->get_shipping_country() ?: WC()->customer->get_billing_country() ?: 'NO';
		}

		// Read any previously saved pickup point from the WC session.
		$session_pickup_id   = '';
		$session_pickup_name = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$session_pickup_id   = WC()->session->get( 'bbi_pickup_point_id', '' );
			$session_pickup_name = WC()->session->get( 'bbi_pickup_point_name', '' );
		}

		wp_localize_script( 'bbi-checkout', 'bbi_checkout_pickup', [
			'rest_url'            => rest_url( 'bbi/v1' ),
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'save_pickup_url'     => \WC_AJAX::get_endpoint( 'bbi_save_pickup' ),
			'save_pickup_nonce'   => wp_create_nonce( 'bbi-save-pickup' ),
			'customer_postcode'   => $customer_postcode,
			'customer_country'    => strtoupper( $customer_country ),
			'session_pickup_id'   => $session_pickup_id,
			'session_pickup_name' => $session_pickup_name,
		] );
		wp_localize_script( 'bbi-checkout', 'bbi_checkout_i18n', [
			'loading'       => __( 'Loading…', 'bbi' ),
			'select_pickup' => __( 'Select pickup point…', 'bbi' ),
			'no_pickup'     => __( 'No pickup points found', 'bbi' ),
		] );

		// WooCommerce Blocks checkout: enrich shipping option cards via Store API extension data + DOM injection.
		wp_enqueue_script( 'bbi-checkout-blocks', BBI_URL . 'assets/js/checkout-blocks.js', [], BBI_VER, true );
		wp_localize_script(
			'bbi-checkout-blocks',
			'bbi_checkout',
			[
				'i18n' => [
					/* translators: 1: expected delivery date, 2: number of working days */
					'expected_delivery_days_singular' => __( 'Expected delivery %1$s (1 working day)', 'bbi' ),
					/* translators: 1: expected delivery date, 2: number of working days */
					'expected_delivery_days_plural'   => __( 'Expected delivery %1$s (%2$d working days)', 'bbi' ),
					/* translators: %s: expected delivery date */
					'expected_delivery_date'          => __( 'Expected delivery %s', 'bbi' ),
					'closest_pickup'                  => __( 'Closest pickup point: ', 'bbi' ),
				],
			]
		);
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
