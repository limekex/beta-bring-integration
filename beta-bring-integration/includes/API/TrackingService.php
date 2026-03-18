<?php
declare( strict_types=1 );

namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\Woo\Logger;

/**
 * Bring Tracking API v2.
 *
 * Retrieves shipment tracking events by tracking number.
 *
 * @see https://developer.bring.com/api/tracking/
 */
class TrackingService {
	private const BASE_URL = 'https://api.bring.com/tracking/api/v2/tracking.json';

	private Client $client;

	public function __construct( SettingsModel $settings ) {
		$this->client = new Client( $settings );
	}

	/**
	 * Get tracking information for a shipment.
	 *
	 * @param string $tracking_number Consignment / package number.
	 * @param string $lang            Language code (no, en, sv, da). Default 'no'.
	 * @return array Decoded API response, or ['error' => '...'] on failure.
	 */
	public function track( string $tracking_number, string $lang = 'no' ): array {
		$url = add_query_arg(
			[
				'q'    => rawurlencode( $tracking_number ),
				'lang' => rawurlencode( $lang ),
			],
			self::BASE_URL
		);

		Logger::info( 'TrackingService: GET ' . $url );

		$resp = $this->client->get( $url );

		if ( ! $resp['success'] ) {
			Logger::error( 'TrackingService: request failed', [
				'tracking' => $tracking_number,
				'code'     => $resp['code'] ?? 'n/a',
				'error'    => $resp['error'] ?? 'unknown',
			] );
			return [ 'error' => $resp['error'] ?? __( 'Tracking request failed', 'bbi' ) ];
		}

		Logger::info( 'TrackingService: response OK', [
			'tracking'  => $tracking_number,
			'has_consignments' => isset( $resp['body']['consignmentSet'] ),
		] );

		return is_array( $resp['body'] ) ? $resp['body'] : [];
	}
}
