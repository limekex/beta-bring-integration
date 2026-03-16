<?php
declare(strict_types=1);

namespace BeTA\Bring\Woo;

use BeTA\Bring\API\ShippingGuideService;
use BeTA\Bring\Model\SettingsModel;

/**
 * WooCommerce Shipping Method for Bring.
 *
 * Registers itself under the method ID 'bbi_bring'.  When added to a
 * shipping zone it queries the Bring Shipping Guide API at checkout and
 * exposes each configured preset as a selectable shipping rate.
 *
 * Presets are defined in WooCommerce → Settings → Shipping → BeTA Bring.
 */
class ShippingMethod extends \WC_Shipping_Method {

	/** Cache TTL for Bring API responses (seconds). */
	private const CACHE_TTL = 900; // 15 minutes

	private SettingsModel        $bbi_settings;
	private ShippingGuideService $guide;

	public function __construct( int $instance_id = 0 ) {
		$this->id                 = 'bbi_bring';
		$this->instance_id        = $instance_id;
		$this->method_title       = __( 'BeTA Bring', 'bbi' );
		$this->method_description = __( 'Show live Bring shipping rates at checkout, based on presets configured under WooCommerce → Settings → Shipping → BeTA Bring.', 'bbi' );
		$this->supports           = [ 'shipping-zones', 'instance-settings' ];

		$this->bbi_settings = new SettingsModel();
		$this->guide        = new ShippingGuideService( $this->bbi_settings );

		$this->init();
	}

	public function init(): void {
		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title', __( 'Bring Shipping', 'bbi' ) );

		add_action(
			'woocommerce_update_options_shipping_' . $this->id,
			[ $this, 'process_admin_options' ]
		);
	}

	public function init_form_fields(): void {
		$this->instance_form_fields = [
			'title' => [
				'title'       => __( 'Method title', 'bbi' ),
				'type'        => 'text',
				'description' => __( 'Label shown to shoppers during checkout.', 'bbi' ),
				'default'     => __( 'Bring Shipping', 'bbi' ),
				'desc_tip'    => true,
			],
			'fallback_cost' => [
				'title'       => __( 'Fallback cost', 'bbi' ),
				'type'        => 'price',
				'description' => __( 'Cost used when the Bring API does not return a price for a preset. Leave blank to hide that preset.', 'bbi' ),
				'default'     => '',
				'desc_tip'    => true,
			],
		];
	}

	/**
	 * Calculate shipping rates for the given package.
	 *
	 * Iterates over all configured presets, queries the Bring Shipping Guide
	 * API for live prices (with a transient cache), filters by the preset's
	 * maxWeightKg, and adds a WooCommerce shipping rate for each eligible
	 * preset.
	 *
	 * @param array $package WooCommerce package array.
	 */
	public function calculate_shipping( $package = [] ): void {
		$presets = $this->bbi_settings->get_presets();

		if ( empty( $presets ) ) {
			return;
		}

		$sender       = $this->bbi_settings->get_sender_array();
		$from_postal  = $sender['postcode'] ?? '';
		$from_country = $sender['country'] ?? 'NO';
		$to_postal    = $package['destination']['postcode'] ?? '';
		$to_country   = $package['destination']['country'] ?? 'NO';

		if ( ! $from_postal || ! $to_postal ) {
			return;
		}

		// Collect the Bring service IDs referenced by the configured presets.
		$service_ids = [];
		foreach ( $presets as $preset ) {
			$sid = $preset['serviceId'] ?? $preset['serviceID'] ?? '';
			if ( $sid ) {
				$service_ids[] = $sid;
			}
		}
		$service_ids = array_values( array_unique( $service_ids ) );

		if ( empty( $service_ids ) ) {
			return;
		}

		// Calculate total package weight in kg.
		$weight_kg = 0.0;
		foreach ( $package['contents'] as $item ) {
			/** @var \WC_Product $product */
			$product = $item['data'];
			if ( $product instanceof \WC_Product && $product->has_weight() ) {
				$converted = wc_get_weight( (float) $product->get_weight(), 'kg' );
				if ( false !== $converted ) {
					$weight_kg += (float) $converted * $item['quantity'];
				}
			}
		}

		$weight_grams = (int) round( $weight_kg * 1000 );

		// When no cart item has a weight configured, skip the live rate query entirely
		// and fall through to the fallback cost below (the API requires weight or dimensions).
		$api_products = $weight_grams > 0
			? $this->get_api_products( $from_postal, $from_country, $to_postal, $to_country, $weight_grams, $service_ids )
			: [];

		$fallback = $this->get_option( 'fallback_cost' );

		foreach ( $presets as $preset_key => $preset ) {
			// The preset data model supports both 'serviceId' and the legacy
			// 'serviceID' capitalisation for backward compatibility.
			$service_id = $preset['serviceId'] ?? $preset['serviceID'] ?? '';
			if ( ! $service_id ) {
				continue;
			}

			// Skip preset if the package exceeds its weight limit.
			$max_weight_kg = isset( $preset['maxWeightKg'] ) ? (float) $preset['maxWeightKg'] : null;
			if ( null !== $max_weight_kg && $weight_kg > $max_weight_kg ) {
				continue;
			}

			$cost = null;

			if ( isset( $api_products[ $service_id ] ) ) {
				$price_data = $api_products[ $service_id ]['price'] ?? [];

				// Prefer net price, fall back to list price.
				$cost = $this->extract_price( $price_data['netPrice'] ?? [] )
					?? $this->extract_price( $price_data['listPrice'] ?? [] );
			}

			// If API returned no price, apply the configured fallback (if any).
			if ( null === $cost ) {
				if ( '' === $fallback || false === $fallback ) {
					continue; // Hide preset when there is no price and no fallback.
				}
				$cost = (float) $fallback;
			}

			$this->add_rate( [
				'id'        => $this->get_rate_id( sanitize_key( $preset_key ) ),
				'label'     => $preset['label'] ?? $preset_key,
				'cost'      => $cost,
				'calc_tax'  => 'per_order',
				'meta_data' => [
					'bbi_preset_key' => $preset_key,
					'bbi_service_id' => $service_id,
				],
			] );
		}
	}

	/**
	 * Fetch products from the Bring Shipping Guide API, using a transient
	 * cache keyed by the route and weight to avoid redundant API calls.
	 *
	 * @param string   $from_postal  Sender postal code.
	 * @param string   $from_country Sender country code (ISO 3166-1 alpha-2).
	 * @param string   $to_postal    Recipient postal code.
	 * @param string   $to_country   Recipient country code.
	 * @param int      $weight_grams Package weight in grams (0 = omit from query).
	 * @param string[] $service_ids  Bring service IDs to query.
	 * @return array<string, array> Products indexed by service ID.
	 */
	private function get_api_products(
		string $from_postal,
		string $from_country,
		string $to_postal,
		string $to_country,
		int $weight_grams,
		array $service_ids = []
	): array {
		$cache_key = 'bbi_sg_' . md5( implode( '|', [ $from_postal, $from_country, $to_postal, $to_country, $weight_grams, implode( ',', $service_ids ) ] ) );

		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$query_args = array_filter(
			[
				'fromcountry' => $from_country,
				'tocountry'   => $to_country,
				'weight'      => $weight_grams > 0 ? (string) $weight_grams : '',
			],
			fn( string $v ): bool => '' !== $v
		);

		$api_data = $this->guide->get_products( $from_postal, $to_postal, $service_ids, $query_args );

		$indexed = [];
		if ( ! empty( $api_data['products'] ) && is_array( $api_data['products'] ) ) {
			foreach ( $api_data['products'] as $product ) {
				$pid = $product['id'] ?? '';
				if ( $pid ) {
					$indexed[ $pid ] = $product;
				}
			}
		}

		set_transient( $cache_key, $indexed, self::CACHE_TTL );

		return $indexed;
	}

	/**
	 * Extract a price amount (including VAT) from a Bring price object.
	 *
	 * @param array $price_obj e.g. { priceWithoutAdditionalServices: { amountWithVAT: "99.00" } }
	 * @return float|null
	 */
	private function extract_price( array $price_obj ): ?float {
		$amount = $price_obj['priceWithoutAdditionalServices']['amountWithVAT']
			?? $price_obj['totalPrice']['amountWithVAT']
			?? null;

		return null !== $amount ? (float) $amount : null;
	}
}
