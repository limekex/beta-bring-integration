<?php
namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;

/**
 * Mybring Customer / User Settings API.
 *
 * Retrieves user account settings and available customer numbers
 * to validate API credentials and enumerate usable accounts.
 *
 * @see https://developer.bring.com/api/mybring/
 */
class CustomerService {
	private const SETTINGS_URL = 'https://www.mybring.com/api/mybring/usersettings';

	private Client        $client;
	private SettingsModel $settings;

	public function __construct( SettingsModel $settings ) {
		$this->settings = $settings;
		$this->client   = new Client( $settings );
	}

	/**
	 * Validate that the configured API credentials return a successful response.
	 *
	 * @return bool
	 */
	public function validate_credentials(): bool {
		if ( ! $this->settings->get_uid() || ! $this->settings->get_api_key() ) {
			return false;
		}

		$resp = $this->client->get( self::SETTINGS_URL );
		return $resp['success'];
	}

	/**
	 * Retrieve Mybring user settings (includes available customer numbers).
	 *
	 * @return array  Decoded response body, or ['error' => '...'] on failure.
	 */
	public function get_user_settings(): array {
		$resp = $this->client->get( self::SETTINGS_URL );

		if ( ! $resp['success'] ) {
			return [ 'error' => $resp['error'] ?? __( 'Customer settings request failed', 'bbi' ) ];
		}

		return is_array( $resp['body'] ) ? $resp['body'] : [];
	}
}
