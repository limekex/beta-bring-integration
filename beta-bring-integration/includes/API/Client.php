<?php
namespace BeTA\Bring\API;

use BeTA\Bring\Model\SettingsModel;
use BeTA\Bring\Woo\Logger;

class Client {
    private SettingsModel $settings;

    public function __construct( SettingsModel $settings ) {
        $this->settings = $settings;
    }

    public function post_json( string $url, array $body, int $timeout = 15 ): array {
        $logger = Logger::get();
        $args = [
            'headers' => $this->prepare_headers(),
            'body'    => wp_json_encode( $body ),
            'timeout' => $timeout,
        ];

        $logger->info( 'bbi: POST ' . $url, [ 'payload' => $body ] );

        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            $logger->error( 'bbi: HTTP error', [ 'err' => $response->get_error_message() ] );
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 429 === (int) $code ) {
            // simple backoff one retry
            usleep( 400000 );
            $logger->info( 'bbi: retry after 429' );
            $response = wp_remote_post( $url, $args );
            if ( is_wp_error( $response ) ) {
                return [ 'success' => false, 'error' => $response->get_error_message() ];
            }
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
        }

        $decoded = json_decode( $body, true );

        return [ 'success' => in_array( $code, [ 200, 201 ], true ), 'code' => $code, 'body' => $decoded ?? $body ];
    }

    public function get( string $url, int $timeout = 15 ): array {
        $logger = Logger::get();
        $args = [ 'headers' => $this->prepare_headers(), 'timeout' => $timeout ];

        $logger->info( 'bbi: GET ' . $url );

        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 429 === (int) $code ) {
            usleep( 400000 );
            $response = wp_remote_get( $url, $args );
            if ( is_wp_error( $response ) ) {
                return [ 'success' => false, 'error' => $response->get_error_message() ];
            }
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
        }

        $decoded = json_decode( $body, true );
        return [ 'success' => in_array( $code, [ 200, 201 ], true ), 'code' => $code, 'body' => $decoded ?? $body ];
    }

    private function prepare_headers(): array {
        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
        ];

        $uid = $this->settings->get_uid();
        $key = $this->settings->get_api_key();

        if ( $uid ) {
            $headers['X-Mybring-API-Uid'] = $uid;
        }
        if ( $key ) {
            $headers['X-Mybring-API-Key'] = $key;
        }
        if ( $this->settings->is_test_mode() ) {
            $headers['X-Bring-Test-Indicator'] = 'true';
        }

        return $headers;
    }
}
