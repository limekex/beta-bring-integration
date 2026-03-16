<?php
namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;

/**
 * Bring Shipping Guide API v2.
 *
 * Retrieves available shipping products and estimated prices/lead-times
 * for a sender/recipient postal code pair.
 *
 * @see https://developer.bring.com/api/shipping-guide_2/
 */
class ShippingGuideService {
	private const PRODUCTS_URL = 'https://api.bring.com/shippingguide/v2/products';

	private Client       $client;
	private SettingsModel $settings;

	public function __construct( SettingsModel $settings ) {
		$this->settings = $settings;
		$this->client   = new Client( $settings );
	}

	/**
	 * Get available shipping products for a sender/recipient postal code pair.
	 *
	 * @param string $from_postal Sender postal code.
	 * @param string $to_postal   Recipient postal code.
	 * @param array  $opts        Optional overrides:
	 *   - fromcountrycode (default: NO)
	 *   - tocountrycode   (default: NO)
	 *   - weightInGrams   (int)
	 *   - volumeInDm3     (float)
	 *   - language        (NO|EN|DK|SE|FI)
	 * @return array  Decoded API response, or ['error' => '...'] on failure.
	 */
	public function get_products( string $from_postal, string $to_postal, array $opts = [] ): array {
		$params = array_merge(
			[
				'frompostalcode'  => $from_postal,
				'topostalcode'    => $to_postal,
				'fromcountrycode' => $this->settings->get_sender_array()['country'] ?? 'NO',
				'tocountrycode'   => 'NO',
			],
			$opts
		);

		$url  = add_query_arg( array_filter( $params, fn( $v ) => '' !== (string) $v ), self::PRODUCTS_URL );
		$resp = $this->client->get( $url );

		if ( ! $resp['success'] ) {
			return [ 'error' => $resp['error'] ?? __( 'Shipping Guide request failed', 'bbi' ), 'products' => [] ];
		}

		return is_array( $resp['body'] ) ? $resp['body'] : [];
	}
}
