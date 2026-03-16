<?php
namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;

/**
 * Bring Pickup Point API.
 *
 * Looks up Bring pickup points (post offices, parcel lockers) near a postal code.
 *
 * @see https://developer.bring.com/api/pickup-point/
 */
class PickupPointService {
	private const BASE_URL = 'https://api.bring.com/pickuppoint/api/pickuppoint';

	private Client $client;

	public function __construct( SettingsModel $settings ) {
		$this->client = new Client( $settings );
	}

	/**
	 * Get pickup points near a postal code.
	 *
	 * @param string $country_code ISO 3166-1 alpha-2 (e.g. "NO").
	 * @param string $postal_code
	 * @param int    $max          Maximum results (1–50, default 10).
	 * @return array  Decoded API response, or ['error' => '...'] on failure.
	 */
	public function get_by_postal_code( string $country_code, string $postal_code, int $max = 10 ): array {
		$max = max( 1, min( 50, $max ) );
		$url = sprintf(
			'%s/%s/postalCode/%s.json?numberOfResults=%d',
			self::BASE_URL,
			rawurlencode( strtoupper( $country_code ) ),
			rawurlencode( $postal_code ),
			$max
		);

		$resp = $this->client->get( $url );

		if ( ! $resp['success'] ) {
			return [ 'error' => $resp['error'] ?? __( 'Pickup Point request failed', 'bbi' ), 'pickupPoints' => [] ];
		}

		return is_array( $resp['body'] ) ? $resp['body'] : [];
	}
}
