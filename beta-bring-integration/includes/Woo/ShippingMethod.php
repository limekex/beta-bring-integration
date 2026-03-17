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

		// Always query the Bring Shipping Guide API so that guiInformation (logo,
		// description, delivery estimate) is available even for stores that don't
		// configure product weights.  get_api_products() omits the weight
		// parameter from the request when weight_grams is zero.
		$api_products = $this->get_api_products( $from_postal, $from_country, $to_postal, $to_country, $weight_grams, $service_ids );

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

			$this->add_rate( [
				'id'        => $this->get_rate_id( sanitize_key( $preset_key ) ),
				'label'     => $label,
				'cost'      => $cost,
				'calc_tax'  => 'per_order',
				'meta_data' => [
					'bbi_preset_key'       => $preset_key,
					'bbi_service_id'       => $service_id,
					'bbi_gui_info'         => $gui_info,
					'bbi_expected_delivery' => $exp_delivery,
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
	 * Enrich the shipping rate label in the cart/checkout with the Bring
	 * logo, estimated delivery date, description text, and closest pickup
	 * point (for pickup-point services).
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

		$extra = '';

		// Logo.
		$logo_url = $gui['logoUrl'] ?? '';
		if ( $logo_url ) {
			$alt_text = $gui['logo'] ?? $gui['displayName'] ?? __( 'Shipping provider logo', 'bbi' );
			$extra .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $alt_text ) . '" class="bbi-shipping-logo" />';
		}

		// Estimated delivery.
		$delivery_date = $delivery['formattedExpectedDeliveryDate'] ?? '';
		$working_days  = isset( $delivery['workingDays'] ) ? (int) $delivery['workingDays'] : 0;
		if ( $delivery_date ) {
			$extra .= '<span class="bbi-delivery-estimate">';
			if ( $working_days > 0 ) {
				$extra .= esc_html(
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
				$extra .= esc_html(
					sprintf(
						/* translators: %s: expected delivery date */
						__( 'Expected delivery %s', 'bbi' ),
						$delivery_date
					)
				);
			}
			$extra .= '</span>';
		}

		// Description / help text.
		$desc = $gui['descriptionText'] ?? '';
		if ( $desc ) {
			$extra .= '<span class="bbi-shipping-desc">' . esc_html( $desc ) . '</span>';
		}

		// Closest pickup point (returned by SERVICEPAKKE / hentested products).
		$pickup = $gui['closestPickupPoint'] ?? '';
		if ( $pickup ) {
			$extra .= '<span class="bbi-pickup-hint">'
				. esc_html__( 'Closest pickup point: ', 'bbi' )
				. esc_html( $pickup )
				. '</span>';
		}

		return $extra ? $label . $extra : $label;
	}
}
