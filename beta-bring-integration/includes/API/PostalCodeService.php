<?php
namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\Woo\Logger;

/**
 * Bring Address / Postal Code API.
 *
 * Looks up city, municipality and county for a given postal code.
 *
 * @see https://developer.bring.com/api/postal-code/
 */
class PostalCodeService {
	private const BASE_URL = 'https://api.bring.com/address/api';

	private Client $client;

	public function __construct( SettingsModel $settings ) {
		$this->client = new Client( $settings );
	}

	/**
	 * Look up city/region for a postal code.
	 *
	 * @param string $country_code ISO 3166-1 alpha-2 (e.g. "NO").
	 * @param string $postal_code
	 * @return array  { postalCode, city, municipality, county, ... } or { error }.
	 */
	public function lookup( string $country_code, string $postal_code ): array {
		$url = sprintf(
			'%s/%s/postal-codes/%s',
			self::BASE_URL,
			rawurlencode( strtoupper( $country_code ) ),
			rawurlencode( $postal_code )
		);

		Logger::info( 'PostalCodeService: GET ' . $url );

		$resp = $this->client->get( $url );

		if ( ! $resp['success'] ) {
			Logger::error( 'PostalCodeService: request failed', [ 'error' => $resp['error'] ?? 'unknown' ] );
			return [ 'error' => $resp['error'] ?? __( 'Postal Code request failed', 'bbi' ) ];
		}

		Logger::info( 'PostalCodeService: response OK' );
		return is_array( $resp['body'] ) ? $resp['body'] : [];
	}
}
