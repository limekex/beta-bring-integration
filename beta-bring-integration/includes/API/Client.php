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
        $args = [
            'headers' => $this->prepare_headers(),
            'body'    => wp_json_encode( $body ),
            'timeout' => $timeout,
        ];

        Logger::debug( 'POST ' . $url, [ 'payload' => $body ] );

        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            Logger::error( 'HTTP error on POST ' . $url, [ 'err' => $response->get_error_message() ] );
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 429 === (int) $code ) {
            // simple backoff one retry
            usleep( 400000 );
            Logger::debug( 'Retry after 429 on POST ' . $url );
            $response = wp_remote_post( $url, $args );
            if ( is_wp_error( $response ) ) {
                Logger::error( 'HTTP error on POST retry ' . $url, [ 'err' => $response->get_error_message() ] );
                return [ 'success' => false, 'error' => $response->get_error_message() ];
            }
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
        }

        $decoded = json_decode( $body, true );

        Logger::debug( 'POST response ' . $url, [ 'code' => $code, 'body' => $decoded ?? $body ] );

        return [ 'success' => in_array( $code, [ 200, 201 ], true ), 'code' => $code, 'body' => $decoded ?? $body ];
    }

    public function get( string $url, int $timeout = 15 ): array {
        $args = [ 'headers' => $this->prepare_headers(), 'timeout' => $timeout ];

        Logger::debug( 'GET ' . $url );

        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) {
            Logger::error( 'HTTP error on GET ' . $url, [ 'err' => $response->get_error_message() ] );
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 429 === (int) $code ) {
            usleep( 400000 );
            Logger::debug( 'Retry after 429 on GET ' . $url );
            $response = wp_remote_get( $url, $args );
            if ( is_wp_error( $response ) ) {
                Logger::error( 'HTTP error on GET retry ' . $url, [ 'err' => $response->get_error_message() ] );
                return [ 'success' => false, 'error' => $response->get_error_message() ];
            }
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
        }

        $decoded = json_decode( $body, true );

        Logger::debug( 'GET response ' . $url, [ 'code' => $code, 'body' => $decoded ?? $body ] );

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
