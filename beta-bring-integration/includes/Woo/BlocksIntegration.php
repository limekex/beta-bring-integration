<?php
declare(strict_types=1);

namespace BeTA\Bring\Woo;

/**
 * WooCommerce Blocks / Store API integration for BBI Bring shipping rates.
 *
 * Registers an extension on the `cart-shipping-rate` Store API endpoint so
 * that the WooCommerce Blocks checkout can access Bring-specific metadata
 * (logo URL, delivery estimate, description, closest pickup point) for each
 * shipping rate.  The data is then consumed by assets/js/checkout-blocks.js
 * which enriches the Blocks checkout shipping option cards in the browser.
 */
class BlocksIntegration {

	public static function init(): void {
		add_action( 'woocommerce_blocks_loaded', [ self::class, 'register_store_api_extension' ] );
	}

	/**
	 * Register the Store API endpoint extension for `cart-shipping-rate`.
	 *
	 * This makes `extensions.bbi` available on every shipping rate object
	 * returned by /wp-json/wc/store/v1/cart so the Blocks checkout JS can
	 * enrich the shipping option cards without an extra API call.
	 */
	public static function register_store_api_extension(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		// 'cart-shipping-rate' was added in WooCommerce 9.x.
		// Older versions only accept cart-item, cart, checkout, product.
		// When the endpoint is unavailable the JS falls back to reading
		// BBI data from each rate's meta_data array (see checkout-blocks.js).
		try {
			woocommerce_store_api_register_endpoint_data( [
				'endpoint'        => 'cart-shipping-rate',
				'namespace'       => 'bbi',
				'data_callback'   => [ self::class, 'get_rate_extension_data' ],
				'schema_callback' => [ self::class, 'get_rate_extension_schema' ],
				'schema_type'     => ARRAY_A,
			] );
		} catch ( \Exception $e ) {
			// Endpoint not supported in this WC version — meta_data fallback is used.
		}
	}

	/**
	 * Return the BBI extension data for a single shipping rate.
	 *
	 * @param \WC_Shipping_Rate $rate The shipping rate.
	 * @return array<string, mixed>
	 */
	public static function get_rate_extension_data( \WC_Shipping_Rate $rate ): array {
		if ( 'bbi_bring' !== $rate->get_method_id() ) {
			return [];
		}

		$meta = $rate->get_meta_data();

		return [
			'gui_info'          => $meta['bbi_gui_info'] ?? [],
			'expected_delivery' => $meta['bbi_expected_delivery'] ?? [],
		];
	}

	/**
	 * Return the JSON schema for the BBI extension data.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_rate_extension_schema(): array {
		return [
			'gui_info'          => [
				'description' => __( 'Bring shipping GUI information (logo, display name, description).', 'bbi' ),
				'type'        => 'object',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'expected_delivery' => [
				'description' => __( 'Bring expected delivery information (date, working days).', 'bbi' ),
				'type'        => 'object',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
		];
	}
}
