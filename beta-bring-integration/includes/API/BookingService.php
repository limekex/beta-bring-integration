<?php
namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\Model\BookingResult;
use BeTA\Bring\Woo\OrderData;
use BeTA\Bring\Woo\Logger;
use WP_Error;

class BookingService {
    public const BOOKING_URL = 'https://www.mybring.com/booking/api/create';

    private SettingsModel $settings;
    private Client $client;

    public function __construct( SettingsModel $settings ) {
        $this->settings = $settings;
        $this->client = new Client( $settings );
    }

    /**
     * Book an order with Bring using preset array.
     *
     * @param \WC_Order $order
     * @param array $preset
     * @param array $params
     * @return BookingResult
     * @throws WP_Error
     */
    public function book_order( $order, array $preset, array $params = [] ): BookingResult {
        $order_id = (int) $order->get_id();

        $weightKg = $params['weight'] ?? OrderData::get_order_weight( $order );
        $tpl = $preset['packageTemplate'] ?? [];

        $payload = [
            'schemaVersion' => 1,
            'testIndicator' => $this->settings->is_test_mode() ? 'true' : 'false',
            'consignments'  => [[
                'product'        => $preset['serviceId'] ?? $preset['serviceID'] ?? '',
                'customerNumber' => $this->settings->get_customer_no(),
                'reference'      => (string) $order->get_order_number(),
                'parties' => [
                    'sender'    => $this->settings->get_sender_array(),
                    'recipient' => OrderData::get_recipient_from_order( $order ),
                ],
                'packages' => [[
                    'weightInKg' => $weightKg,
                    'lengthInCm' => $tpl['length'] ?? 30,
                    'widthInCm'  => $tpl['width'] ?? 20,
                    'heightInCm' => $tpl['height'] ?? 10,
                ]],
                'additionalServices' => $preset['vas'] ?? [],
            ]],
        ];

        Logger::info( 'Booking request', [ 'order' => $order_id, 'payload' => $payload ] );

        // Simulate if in test mode and missing credentials
        if ( $this->settings->is_test_mode() && ( ! $this->settings->get_uid() || ! $this->settings->get_api_key() ) ) {
            $now = gmdate( 'c' );
            $fake = [
                'consignmentNo' => 'TEST-' . $order_id,
                'labelUrl'      => home_url( '/?bbi_label=test-' . $order_id ),
                'trackingUrl'   => home_url( '/?bbi_track=test-' . $order_id ),
            ];

            $result = new BookingResult( $fake['consignmentNo'], $fake['labelUrl'], $fake['trackingUrl'], $preset['serviceId'] ?? '', $now, $fake );

            return $result;
        }

        $resp = $this->client->post_json( self::BOOKING_URL, $payload );
        if ( ! $resp['success'] ) {
            throw new WP_Error( 'bbi_api_error', __( 'Booking failed: ', 'bbi' ) . ( $resp['error'] ?? __( 'Unknown', 'bbi' ) ) );
        }

        $body = is_array( $resp['body'] ) ? $resp['body'] : [];

        // Parse response for consignment and label
        $consignment = $body['consignmentNo'] ?? $body['consignmentNumber'] ?? ($body['consignments'][0]['consignmentNo'] ?? null);
        $labelUrl    = $body['labelUrl'] ?? $body['documentUrl'] ?? null;
        $trackingUrl = $body['trackingUrl'] ?? null;

        $now = gmdate( 'c' );

        if ( ! $consignment ) {
            throw new WP_Error( 'bbi_response_invalid', __( 'Invalid response from Bring API', 'bbi' ) );
        }

        $result = new BookingResult( $consignment, $labelUrl, $trackingUrl, $preset['serviceId'] ?? '', $now, $body );

        return $result;
    }
}
