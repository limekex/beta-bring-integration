<?php
namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\Woo\Logger;

/**
 * Bring Shipping Guide API v2.
 *
 * Retrieves available shipping products and estimated prices/lead-times
 * for a sender/recipient postal code pair.
 *
 * The API requires a POST request whose body contains a `consignments` array.
 * The response also wraps products inside `consignments[0].products`.
 *
 * @see https://developer.bring.com/api/shipping-guide_2/
 */
class ShippingGuideService {
	private const PRODUCTS_URL = 'https://api.bring.com/shippingguide/v2/products';

	private Client        $client;
	private SettingsModel $settings;

	public function __construct( SettingsModel $settings ) {
		$this->settings = $settings;
		$this->client   = new Client( $settings );
	}

	/**
	 * Get available shipping products for a sender/recipient postal code pair.
	 *
	 * Sends a POST request to the Bring Shipping Guide v2 API with a JSON body
	 * containing the consignment details.  The API returns prices, GUI info
	 * (logo, description), and expected delivery dates inside the response
	 * property `consignments[0].products`.
	 *
	 * @param string   $from_postal  Sender postal code.
	 * @param string   $to_postal    Recipient postal code.
	 * @param string[] $product_ids  Bring service IDs to query (e.g. SERVICEPAKKE).
	 * @param array    $opts         Optional overrides:
	 *   - fromcountry   (default: sender country from settings, e.g. NO)
	 *   - tocountry     (default: NO)
	 *   - weight_grams  (int, package weight in grams; default 1)
	 *   - language      (no|en|da|se|fi; default no)
	 * @return array  Decoded API response body, or ['error' => '...'] on failure.
	 */
	public function get_products( string $from_postal, string $to_postal, array $product_ids = [], array $opts = [] ): array {
		$from_country = $opts['fromcountry'] ?? $this->settings->get_sender_array()['country'] ?? 'NO';
		$to_country   = $opts['tocountry'] ?? 'NO';
		$language     = $opts['language'] ?? 'no';

		// grossWeight is in grams; use at least 1 g so the API always gets a valid package.
		$weight_grams = isset( $opts['weight_grams'] ) ? max( 1, (int) $opts['weight_grams'] ) : 1;

		$customer_no = $this->settings->get_customer_no() ?? '';

		// Build the consignment object — both packages and products live here.
		$consignment = [
			'fromPostalCode'  => $from_postal,
			'fromCountryCode' => $from_country,
			'toPostalCode'    => $to_postal,
			'toCountryCode'   => $to_country,
			'packages'        => [ [ 'grossWeight' => $weight_grams ] ],
		];

		if ( $customer_no ) {
			$consignment['consignorCustomerNo'] = $customer_no;
		}

		if ( ! empty( $product_ids ) ) {
			$consignment['products'] = array_map( fn( string $id ) => [ 'id' => $id ], $product_ids );
		}

		$body = [
			'language'             => $language,
			'withPrice'            => true,
			'withGuiInformation'   => true,
			'withExpectedDelivery' => true,
			'consignments'         => [ $consignment ],
		];

		Logger::info( 'ShippingGuideService: POST', [
			'from' => $from_postal . ' (' . $from_country . ')',
			'to'   => $to_postal . ' (' . $to_country . ')',
			'weight_g' => $weight_grams,
			'products' => $product_ids,
		] );

		$resp = $this->client->post_json( self::PRODUCTS_URL, $body );

		if ( ! $resp['success'] ) {
			Logger::error( 'ShippingGuideService: request failed', [
				'code'  => $resp['code'] ?? 'n/a',
				'error' => $resp['error'] ?? 'unknown',
			] );
			return [ 'error' => $resp['error'] ?? __( 'Shipping Guide request failed', 'bbi' ), 'consignments' => [] ];
		}

		$result = is_array( $resp['body'] ) ? $resp['body'] : [];
		$product_count = count( $result['consignments'][0]['products'] ?? [] );
		Logger::info( 'ShippingGuideService: response OK', [ 'product_count' => $product_count ] );

		return $result;
	}
}
