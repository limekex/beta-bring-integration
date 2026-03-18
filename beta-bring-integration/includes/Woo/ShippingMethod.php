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

	/**
	 * Maps current Bring Shipping Guide v2 numeric product codes to the legacy
	 * string codes that older preset configurations may still use.
	 *
	 * The API always responds with numeric IDs regardless of what string alias
	 * was sent in the request, so we need to also expose products under their
	 * legacy aliases so that existing preset configs keep working.
	 *
	 * @var array<string, string[]>  numeric_id → list of legacy string aliases
	 */
	private const LEGACY_CODE_MAP = [
		// 3570 = non-trackable mailbox parcel; 3584 = trackable variant.
		// Both share the PAKKE_I_POSTKASSEN legacy alias. The trackable (3584)
		// entry intentionally overwrites the 3570 entry so that when a preset
		// uses the legacy alias it resolves to the tracked product.
		'3570' => [ 'PAKKE_I_POSTKASSEN' ],
		'3584' => [ 'PAKKE_I_POSTKASSEN' ],
		'5800' => [ 'SERVICEPAKKE', 'PAKKE_TIL_HENTESTED' ],
		'5600' => [ 'PA_DOREN', 'PAKKE_LEVERT_HJEM' ],
		'1000' => [ 'BPAKKE_DOR-DOR', 'BEDRIFTSPAKKE' ],
		'1002' => [ 'EKSPRESS09' ],
		'0330' => [ 'BUSINESS_PARCEL' ],
		'0340' => [ 'PICKUP_PARCEL' ],
		'3110' => [ 'MINIPAKKE' ],
		'4850' => [ 'EKSPRESS_NESTE_DAG' ],
	];

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
			Logger::debug( 'calculate_shipping: No presets configured — skipping rate calculation.' );
			return;
		}

		$sender       = $this->bbi_settings->get_sender_array();
		$from_postal  = $sender['postcode'] ?? '';
		$from_country = $sender['country'] ?? 'NO';
		$to_postal    = $package['destination']['postcode'] ?? '';
		$to_country   = $package['destination']['country'] ?? 'NO';

		if ( ! $from_postal || ! $to_postal ) {
			Logger::debug( 'calculate_shipping: Missing postal code — from=' . $from_postal . ' to=' . $to_postal );
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

		// Always query the Bring Shipping Guide API so that guiInformation (logo,
		// description, delivery estimate) is available even for stores that don't
		// configure product weights.  ShippingGuideService enforces a minimum of
		// 1 gram for the package grossWeight so the API always receives a valid body.
		$api_products = $this->get_api_products( $from_postal, $from_country, $to_postal, $to_country, $weight_grams, $service_ids );

		if ( empty( $api_products ) ) {
			Logger::debug( 'calculate_shipping: Shipping Guide returned no products.', [
				'from' => $from_postal,
				'to'   => $to_postal,
				'ids'  => $service_ids,
			] );
		}

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

			$cost         = null;
			$gui_info     = [];
			$exp_delivery = [];

			if ( isset( $api_products[ $service_id ] ) ) {
				$api_product  = $api_products[ $service_id ];
				$price_data   = $api_product['price'] ?? [];
				$gui_info     = $api_product['guiInformation'] ?? [];
				$exp_delivery = $api_product['expectedDelivery'] ?? [];

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

			// Use the API display name if the preset has no custom label configured.
			$label = $preset['label'] ?? '';
			if ( '' === $label && ! empty( $gui_info['displayName'] ) ) {
				$label = $gui_info['displayName'];
			}
			if ( '' === $label ) {
				$label = $preset_key;
			}

			// Auto-detect pickup services: Bring's Shipping Guide returns
			// closestPickupPoint in guiInformation for pickup services, or
			// the preset can explicitly set requiresPickupPoint.
			$requires_pickup = ! empty( $preset['requiresPickupPoint'] )
				|| ! empty( $gui_info['closestPickupPoint'] );

			$this->add_rate( [
				'id'        => $this->get_rate_id( sanitize_key( $preset_key ) ),
				'label'     => $label,
				'cost'      => $cost,
				'calc_tax'  => 'per_order',
				'meta_data' => [
					'bbi_preset_key'        => $preset_key,
					'bbi_service_id'        => $service_id,
					'bbi_gui_info'          => $gui_info,
					'bbi_expected_delivery' => $exp_delivery,
					'bbi_requires_pickup'   => $requires_pickup,
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
		// Include BBI_VER in the cache key so that stale transients are
		// automatically discarded when the plugin is updated.
		$cache_key = 'bbi_sg_' . md5( implode( '|', [ BBI_VER, $from_postal, $from_country, $to_postal, $to_country, $weight_grams, implode( ',', $service_ids ) ] ) );

		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$opts = [
			'fromcountry'  => $from_country,
			'tocountry'    => $to_country,
			'weight_grams' => $weight_grams,
		];

		$api_data = $this->guide->get_products( $from_postal, $to_postal, $service_ids, $opts );

		// If the API returned an error, do NOT cache — let the next request
		// retry so rates appear as soon as the issue is resolved.
		if ( isset( $api_data['error'] ) ) {
			Logger::error( 'Shipping Guide API error — rates will be unavailable until resolved.', [
				'error'        => $api_data['error'],
				'from_postal'  => $from_postal,
				'to_postal'    => $to_postal,
				'service_ids'  => $service_ids,
			] );
			return [];
		}

		$indexed  = [];
		$products = $api_data['consignments'][0]['products'] ?? [];
		if ( is_array( $products ) ) {
			foreach ( $products as $product ) {
				$pid = $product['id'] ?? '';
				if ( ! $pid ) {
					continue;
				}
				// Index by the numeric product ID returned by the API.
				$indexed[ $pid ] = $product;

				// Also index by any known legacy string aliases for this product
				// so that existing preset configs using old Bring string codes
				// (e.g. "SERVICEPAKKE") continue to resolve correctly.
				foreach ( self::LEGACY_CODE_MAP[ $pid ] ?? [] as $alias ) {
					$indexed[ $alias ] = $product;
				}
			}
		}

		set_transient( $cache_key, $indexed, self::CACHE_TTL );

		return $indexed;
	}

	/**
	 * Extract a price amount (tax-exclusive) from a Bring price object.
	 *
	 * WooCommerce shipping rates expect a tax-exclusive cost; the store's
	 * configured shipping tax class is then applied on top.  Using
	 * amountWithoutVAT ensures the displayed cart price matches the
	 * API's amountWithVAT once WooCommerce adds the correct VAT.
	 *
	 * @param array $price_obj e.g. { priceWithoutAdditionalServices: { amountWithoutVAT: "46.65" } }
	 * @return float|null
	 */
	private function extract_price( array $price_obj ): ?float {
		$amount = $price_obj['priceWithoutAdditionalServices']['amountWithoutVAT']
			?? $price_obj['totalPrice']['amountWithoutVAT']
			?? null;

		return null !== $amount ? (float) $amount : null;
	}

	/**
	 * Enrich the shipping rate label in the cart/checkout.
	 *
	 * Replaces the default "Name: kr123,00" label with a structured card
	 * layout:  logo + name on the left, price on the right, delivery
	 * estimate below, and an accordion panel for description / pickup.
	 *
	 * Registered unconditionally via Plugin::init() so it fires even when
	 * the shipping method is not re-instantiated (i.e. cached-rate requests).
	 *
	 * Hooked to `woocommerce_cart_shipping_method_full_label`.
	 *
	 * @param string            $label The current label HTML.
	 * @param \WC_Shipping_Rate $rate  The shipping rate object.
	 * @return string
	 */
	public static function filter_rate_label( string $label, \WC_Shipping_Rate $rate ): string {
		if ( 'bbi_bring' !== $rate->get_method_id() ) {
			return $label;
		}

		$meta     = $rate->get_meta_data();
		$gui      = $meta['bbi_gui_info'] ?? [];
		$delivery = $meta['bbi_expected_delivery'] ?? [];

		if ( empty( $gui ) && empty( $delivery ) ) {
			return $label;
		}

		// ── Parse the original WooCommerce label ──────────────────────────────
		// WooCommerce generates "Label: <span ...>kr123,00</span>".
		// Extract the name (before the colon) and keep the price span intact.
		$name  = '';
		$price = '';
		if ( preg_match( '/^(.+?):\s*(<span\b.+<\/span>|<bdi\b.+<\/bdi>)\s*$/s', $label, $m ) ) {
			$name  = trim( $m[1] );
			$price = trim( $m[2] );
		} else {
			// Fallback: treat the whole label as the name.
			$name = wp_strip_all_tags( $label );
		}

		// ── Header row: [logo + name]  [price] ──────────────────────────────
		$left = '';

		$logo_url = $gui['logoUrl'] ?? '';
		if ( $logo_url ) {
			$alt_text = $gui['logo'] ?? $gui['displayName'] ?? __( 'Shipping provider logo', 'bbi' );
			$left .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $alt_text ) . '" class="bbi-shipping-logo" />';
		}

		$left .= '<span class="bbi-shipping-name">' . esc_html( $name ) . '</span>';

		$right = '';
		if ( $price ) {
			$right = '<span class="bbi-shipping-price">' . $price . '</span>';
		}

		$output  = '<span class="bbi-shipping-header">';
		$output .= '<span class="bbi-shipping-header-left">' . $left . '</span>';
		if ( $right ) {
			$output .= '<span class="bbi-shipping-header-right">' . $right . '</span>';
		}
		$output .= '</span>';

		// ── Delivery estimate ────────────────────────────────────────────────
		$delivery_date = $delivery['formattedExpectedDeliveryDate'] ?? '';
		$working_days  = isset( $delivery['workingDays'] ) ? (int) $delivery['workingDays'] : 0;
		if ( $delivery_date ) {
			$output .= '<span class="bbi-delivery-estimate">';
			if ( $working_days > 0 ) {
				$output .= esc_html(
					sprintf(
						/* translators: 1: expected delivery date, 2: number of working days */
						_n(
							'Expected delivery %1$s (%2$d working day)',
							'Expected delivery %1$s (%2$d working days)',
							$working_days,
							'bbi'
						),
						$delivery_date,
						$working_days
					)
				);
			} else {
				$output .= esc_html(
					sprintf(
						/* translators: %s: expected delivery date */
						__( 'Expected delivery %s', 'bbi' ),
						$delivery_date
					)
				);
			}
			$output .= '</span>';
		}

		// ── Accordion details (description + pickup) ─────────────────────────
		$details = '';

		$desc = $gui['descriptionText'] ?? '';
		if ( $desc ) {
			$details .= '<span class="bbi-shipping-desc">' . esc_html( $desc ) . '</span>';
		}

		$pickup = $gui['closestPickupPoint'] ?? '';
		if ( $pickup ) {
			$details .= '<span class="bbi-pickup-hint">'
				. esc_html__( 'Closest pickup point: ', 'bbi' )
				. esc_html( $pickup )
				. '</span>';
		}

		// Add a pickup point selector for services that require it.
		// Detect from rate meta OR from the Bring API's closestPickupPoint.
		$requires_pickup = ! empty( $meta['bbi_requires_pickup'] ) || ! empty( $pickup );
		if ( $requires_pickup ) {
			$details .= '<span class="bbi-pickup-selector" data-service-id="' . esc_attr( $meta['bbi_service_id'] ?? '' ) . '">'
				. '<label class="bbi-pickup-label">' . esc_html__( 'Choose pickup point', 'bbi' ) . '</label>'
				. '<select class="bbi-pickup-select" name="bbi_pickup_point_id">'
				. '<option value="">' . esc_html__( 'Loading pickup points…', 'bbi' ) . '</option>'
				. '</select>'
				. '</span>';
		}

		if ( $details ) {
			$output .= '<span class="bbi-shipping-details">' . $details . '</span>';
		}

		return $output;
	}
}
